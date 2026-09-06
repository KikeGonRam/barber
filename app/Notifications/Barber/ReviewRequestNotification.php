<?php

namespace App\Notifications\Barber;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invita al cliente a dejar una reseña despues de completar su cita.
 * Se despacha con retraso para no colisionar con el correo de "completada".
 */
class ReviewRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    /**
     * Database siempre; correo opcional segun preferencia del cliente.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Correo invitando a dejar resena, con enlace directo al perfil del
     * barbero que atendio (ver reviewUrl()).
     */
    public function toMail(object $notifiable): MailMessage
    {
        $barbero = $this->appointment->barber?->user?->name ?? 'tu barbero';

        return (new MailMessage)
            ->subject('¿Como estuvo tu experiencia en UrbanBlade?')
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Tu opinion',
                'title' => '¿Como te fue con '.$barbero.'?',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Gracias por tu visita. Tu opinion ayuda a otros caballeros a elegir y a nuestro equipo a mejorar.',
                'rows' => [
                    'Barbero' => $barbero,
                    'Servicio' => $this->appointment->service?->nombre ?? 'N/D',
                    'Fecha' => optional($this->appointment->fecha)->format('d/m/Y') ?? 'N/D',
                ],
                'ctaLabel' => 'Dejar mi resena',
                'ctaUrl' => $this->reviewUrl(),
            ]);
    }

    /**
     * Payload para el canal database (centro de notificaciones in-app).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'review_request',
            'appointment_id' => $this->appointment->id,
            'title' => 'Cuentanos tu experiencia',
            'message' => 'Deja una resena de tu ultima visita.',
            'url' => $this->reviewUrl(),
        ];
    }

    /**
     * Lleva directo al perfil del barbero (donde se deja la resena); si no
     * hay barbero asociado o falla la ruta, cae al listado general.
     */
    private function reviewUrl(): string
    {
        $frontend = config('app.frontend_url');

        try {
            $barber = $this->appointment->barber;

            return $barber
                ? "{$frontend}/barbers/{$barber->slug}"
                : "{$frontend}/barbers";
        } catch (\Throwable $e) {
            return $frontend;
        }
    }
}
