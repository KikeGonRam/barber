<?php

namespace App\Notifications\Barber;

use App\Models\BarberReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a administración de una reseña de 1-2 estrellas (ver
 * BarberReviewService::FLAGGED_RATING_THRESHOLD), con el comentario completo
 * incluido: antes de esto ninguna reseña, buena o mala, generaba ningún
 * aviso — solo se veía si alguien entraba a revisar el perfil del barbero a
 * mano. Incluye un link al panel de reseñas en Nuxt (frontend-urban).
 */
class BarberReviewFlaggedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $barberName,
        public readonly string $clientName,
        public readonly int $rating,
        public readonly ?string $comment,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reseña de {$this->rating}★ para {$this->barberName}")
            ->markdown('emails.message', [
                'accent' => '#ef4444',
                'badge' => $this->rating.'★',
                'title' => 'Reseña que requiere atención',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => "{$this->clientName} dejó una reseña de {$this->rating} estrella(s) para {$this->barberName}.",
                'rows' => array_filter([
                    'Barbero' => $this->barberName,
                    'Cliente' => $this->clientName,
                    'Calificación' => $this->rating.' / 5',
                    'Comentario' => $this->comment,
                ]),
                'ctaLabel' => 'Ver reseñas',
                'ctaUrl' => $this->reviewsUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'barber_review_flagged',
            'title' => 'Reseña de '.$this->rating.'★',
            'message' => "{$this->clientName} calificó a {$this->barberName} con {$this->rating}/5.",
            'barber_name' => $this->barberName,
            'client_name' => $this->clientName,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'url' => $this->reviewsUrl(),
        ];
    }

    public static function fromReview(BarberReview $review): self
    {
        return new self(
            barberName: $review->barber?->user?->name ?? $review->barber?->nombre ?? 'Barbero',
            clientName: $review->client?->user?->name ?? 'Cliente',
            rating: (int) $review->rating,
            comment: $review->comment,
        );
    }

    private function reviewsUrl(): string
    {
        return config('app.frontend_url').'/reviews';
    }
}
