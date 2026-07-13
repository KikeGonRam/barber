<?php

namespace App\Notifications;

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

    private function reviewUrl(): string
    {
        try {
            $barber = $this->appointment->barber;

            return $barber
                ? route('client.barberos.show', $barber)
                : route('client.barberos.index');
        } catch (\Throwable $e) {
            return url('/');
        }
    }
}
