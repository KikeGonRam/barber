<?php

namespace App\Notifications\Campaign;

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
        // Si viene de una campana, habilita seguimiento de apertura/clic.
        public readonly ?string $campaignId = null,
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

    /**
     * Arma el correo de promocion; agrega tracking de apertura/clic solo si
     * viene de una campana (ver bloque de campaignId abajo).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = [
            'accent' => '#d4af37',
            'badge' => 'Promocion',
            'title' => $this->titulo,
            'greeting' => 'Hola '.$notifiable->name.',',
            'intro' => $this->cuerpo,
            'ctaLabel' => $this->ctaLabel ?? 'Reservar ahora',
            'ctaUrl' => $this->ctaUrl ?? $this->defaultUrl(),
        ];

        // Seguimiento por destinatario cuando la promo pertenece a una campana:
        // el boton pasa por el redirector de clic y se inserta el pixel de apertura.
        if ($this->campaignId && $notifiable->id) {
            $params = ['campaign' => $this->campaignId, 'user' => (string) $notifiable->id];
            $data['ctaUrl'] = route('track.click', $params);
            $data['pixel'] = route('track.open', $params);
        }

        return (new MailMessage)
            ->subject($this->titulo)
            ->markdown('emails.message', $data);
    }

    /**
     * Payload para el canal database (centro de notificaciones in-app).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'promotion',
            'title' => $this->titulo,
            'message' => $this->cuerpo,
            'url' => $this->ctaUrl ?? $this->defaultUrl(),
        ];
    }

    /**
     * URL por defecto si la promo no trae una propia; intenta la pagina
     * publica de servicios y cae al dashboard o a la raiz.
     */
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
