<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de promocion/campana. Solo llega a quien tiene activado el opt-in
 * de "promociones" (marketing), independiente de las notificaciones
 * transaccionales.
 */
class PromotionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $titulo,
        public readonly string $cuerpo,
        public readonly ?string $ctaLabel = null,
        public readonly ?string $ctaUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        // Requiere consentimiento de marketing.
        if (method_exists($notifiable, 'wantsNotificationChannel') && ! $notifiable->wantsNotificationChannel('promociones')) {
            return [];
        }

        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('in_app')) {
            $channels[] = 'database';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->titulo)
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Promocion',
                'title' => $this->titulo,
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => $this->cuerpo,
                'ctaLabel' => $this->ctaLabel ?? 'Reservar ahora',
                'ctaUrl' => $this->ctaUrl ?? $this->defaultUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'promotion',
            'title' => $this->titulo,
            'message' => $this->cuerpo,
            'url' => $this->ctaUrl ?? $this->defaultUrl(),
        ];
    }

    private function defaultUrl(): string
    {
        foreach (['services.public.index', 'dashboard'] as $name) {
            try {
                return route($name);
            } catch (\Throwable $e) {
                // siguiente
            }
        }

        return url('/');
    }
}
