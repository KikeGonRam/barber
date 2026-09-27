<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo de bienvenida al crear la cuenta (registro con correo o primer inicio con Google).
 * Se manda desde AppServiceProvider al escuchar Registered; si el correo falla, el alta sigue.
 */
class WelcomeNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject('Bienvenido a UrbanBlade')
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Cuenta creada',
                'title' => 'Te damos la bienvenida',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Tu cuenta en UrbanBlade está lista. Desde ahora puedes reservar con horarios reales, '
                    .'pagar en línea, juntar puntos por cada visita y descargar tus facturas.',
                'rows' => [
                    'Reserva' => 'Elige servicio, barbero y hora en menos de un minuto',
                    'Puntos' => 'Ganas puntos en cada cita completada',
                    'Facturas' => 'Siempre disponibles en "Mis facturas"',
                ],
                'ctaLabel' => 'Reservar mi primera cita',
                'ctaUrl' => $frontend.'/reservar',
            ]);
    }
}
