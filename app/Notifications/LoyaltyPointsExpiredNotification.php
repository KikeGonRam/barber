<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de caducidad total del saldo de puntos por 365+ dias sin actividad
 * (ver LoyaltyService::applyInactivityLifecycle) — misma logica que usa
 * Spin Premia (OXXO): los puntos no acumulados son de por vida, hay que
 * seguir viniendo para conservarlos.
 */
class LoyaltyPointsExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $puntosPerdidos) {}

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
            ->subject('Tus puntos UrbanBlade caducaron')
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Puntos vencidos',
                'title' => 'Hola, '.$notifiable->name,
                'intro' => 'Por llevar más de un año sin visitarnos, tus **'.$this->puntosPerdidos.' puntos** acumulados caducaron. Agenda tu próxima cita para empezar a sumar de nuevo.',
                'rows' => [
                    'Puntos perdidos' => (string) $this->puntosPerdidos,
                ],
                'ctaLabel' => 'Reservar una cita',
                'ctaUrl' => route('dashboard'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'loyalty_points_expired',
            'puntos_perdidos' => $this->puntosPerdidos,
            'title' => 'Tus puntos caducaron',
            'message' => "Perdiste {$this->puntosPerdidos} puntos por más de un año sin actividad.",
        ];
    }
}
