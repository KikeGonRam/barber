<?php

namespace App\Services\Barber;

use App\Exceptions\Domain\DuplicateReviewException;
use App\Exceptions\Domain\ReviewNotEligibleException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Client;
use App\Models\User;
use App\Notifications\Barber\BarberReviewFlaggedNotification;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Facades\Notification;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Punto único para crear una reseña de barbero: antes esta lógica (elegibilidad,
 * anti-duplicado, puntos de lealtad, estadísticas denormalizadas del barbero)
 * vivía duplicada e inconsistente entre ClientBarberController (web) y
 * CatalogController (API móvil) — solo la API sincronizaba
 * calificacion_promedio/total_resenas del barbero, la web no. Ahora ambos
 * delegan aquí.
 */
class BarberReviewService
{
    // Calificaciones en o por debajo de este umbral notifican a administración
    // (ver BarberReviewFlaggedNotification) para que alguien le dé seguimiento;
    // antes ninguna reseña, buena o mala, disparaba ningún aviso.
    const FLAGGED_RATING_THRESHOLD = 2;

    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function hasCompletedAppointment(Client $client, Barber $barber): bool
    {
        return Appointment::where('barber_id', (string) $barber->id)
            ->where('client_id', (string) $client->id)
            ->where('estado', 'completada')
            ->exists();
    }

    public function alreadyReviewed(Client $client, Barber $barber): bool
    {
        return BarberReview::where('barber_id', (string) $barber->id)
            ->where('client_id', (string) $client->id)
            ->exists();
    }

    /**
     * Crea la reseña, sincroniza las estadísticas del barbero, otorga puntos
     * de lealtad y notifica a administración si la calificación es baja.
     *
     * @throws ReviewNotEligibleException si el cliente nunca tuvo una cita completada con el barbero
     * @throws DuplicateReviewException si el cliente ya reseñó a este barbero
     */
    public function submit(Client $client, Barber $barber, int $rating, ?string $comment): BarberReview
    {
        if (! $this->hasCompletedAppointment($client, $barber)) {
            throw new ReviewNotEligibleException('Solo puedes reseñar barberos con los que hayas tenido una cita completada.');
        }

        if ($this->alreadyReviewed($client, $barber)) {
            throw new DuplicateReviewException('Ya dejaste una reseña para este barbero.');
        }

        try {
            $review = BarberReview::create([
                'barber_id' => (string) $barber->id,
                'client_id' => (string) $client->id,
                'rating' => $rating,
                'comment' => $comment,
            ]);
        } catch (BulkWriteException $e) {
            // El chequeo alreadyReviewed() de arriba no es atomico: si dos
            // requests del mismo cliente llegan a la vez, el indice unico
            // (barber_id, client_id) es el que realmente lo impide.
            throw new DuplicateReviewException('Ya dejaste una reseña para este barbero.');
        }

        $this->syncBarberRatingStats($barber);

        $this->loyalty->awardResenaPoints($client, (string) $review->id);

        if ($rating <= self::FLAGGED_RATING_THRESHOLD) {
            $this->notifyAdminsOfFlaggedReview($review->fresh(['barber.user', 'client.user']));
        }

        return $review;
    }

    // Mantiene calificacion_promedio/total_resenas del documento del barbero
    // en sincronia con sus reseñas reales (denormalizado para listados rapidos).
    private function syncBarberRatingStats(Barber $barber): void
    {
        $allReviews = BarberReview::where('barber_id', (string) $barber->id)->get(['rating']);

        $barber->calificacion_promedio = round($allReviews->avg('rating'), 2);
        $barber->total_resenas = $allReviews->count();
        $barber->save();
    }

    private function notifyAdminsOfFlaggedReview(BarberReview $review): void
    {
        $admins = User::whereRoleName('administrador')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, BarberReviewFlaggedNotification::fromReview($review));
    }
}
