<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Domain\DuplicateReviewException;
use App\Exceptions\Domain\ReviewNotEligibleException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\BarberSchedule;
use App\Models\Work;
use App\Services\Barber\BarberReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vitrina pública/cliente de barberos: listado, perfil con trabajos y reseñas,
 * y creación de reseñas por clientes que ya tuvieron una cita completada.
 */
class ClientBarberController extends Controller
{
    public function __construct(private readonly BarberReviewService $reviews) {}

    // Lista barberos activos con su conteo de citas completadas (calculado en PHP, no withCount).
    public function index(): View
    {
        $barbers = Barber::query()
            ->where('activo', true)
            ->with('user:id,name,created_at')
            ->get();

        $barberIds = $barbers->pluck('id')->map(fn ($id) => (string) $id)->toArray();

        if (! empty($barberIds)) {
            $citasCounts = Appointment::whereIn('barber_id', $barberIds)
                ->where('estado', 'completada')
                ->get(['barber_id'])
                ->groupBy('barber_id')
                ->map->count();

            $barbers->each(fn ($b) => $b->citas_completadas = $citasCounts->get((string) $b->id, 0));
        }

        return view('client.barbers.index', compact('barbers'));
    }

    // Perfil público del barbero: trabajos, reseñas, disponibilidad de hoy y si el cliente actual puede reseñar.
    public function show(Barber $barber): View
    {
        $barber->load('user:id,name,email,created_at');

        $works = Work::where('barbero_id', (string) $barber->user_id)
            ->with(['images', 'reactions', 'comments'])
            ->latest()
            ->limit(12)
            ->get();

        $citasCompletadas = Appointment::where('barber_id', (string) $barber->id)
            ->where('estado', 'completada')
            ->count();

        $reviews = BarberReview::where('barber_id', (string) $barber->id)
            ->with('client.user:id,name')
            ->latest()
            ->get();

        $avgRating = $reviews->isNotEmpty() ? round($reviews->avg('rating'), 1) : null;
        $totalReviews = $reviews->count();
        $ratingCounts = $reviews->groupBy('rating')->map->count();

        $yearsExp = max(1, (int) $barber->user?->created_at?->diffInYears(now()));

        $disponibleHoy = BarberSchedule::where('barber_id', (string) $barber->id)
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_working', true)
            ->exists();

        $client = auth()->user()?->clientProfile;
        $alreadyReviewed = false;
        $canReview = false;

        if ($client) {
            $alreadyReviewed = $this->reviews->alreadyReviewed($client, $barber);
            $canReview = ! $alreadyReviewed && $this->reviews->hasCompletedAppointment($client, $barber);
        }

        return view('client.barbers.show', compact(
            'barber', 'works', 'citasCompletadas',
            'reviews', 'avgRating', 'totalReviews', 'ratingCounts',
            'yearsExp', 'disponibleHoy', 'canReview', 'alreadyReviewed',
        ));
    }

    // Crea una reseña; la elegibilidad, anti-duplicado, puntos de lealtad,
    // estadísticas del barbero y el aviso a admin por calificación baja
    // viven en BarberReviewService (compartido con la API móvil).
    public function storeReview(Request $request, Barber $barber): RedirectResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client, 403);

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        try {
            $this->reviews->submit($client, $barber, (int) $validated['rating'], $validated['comment'] ?? null);
        } catch (ReviewNotEligibleException|DuplicateReviewException $e) {
            return back()->withErrors(['rating' => $e->getMessage()]);
        }

        return back()->with('status', '¡Gracias por tu reseña! +5 puntos de lealtad añadidos.');
    }
}
