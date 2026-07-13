<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $subject,
        public readonly string $title,
        public readonly string $message,
        // Accion del correo/notificacion. Por defecto apunta al panel del
        // cliente; barbero/recepcion pasan su propia etiqueta y ruta.
        public readonly ?string $actionLabel = null,
        public readonly ?string $actionUrl = null,
        // Color de acento y etiqueta de estado del correo (verde=confirmada,
        // rojo=cancelada, azul=barbero, etc.).
        public readonly string $accent = '#d4af37',
        public readonly ?string $badge = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('in_app')) {
            $channels[] = 'database';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels ?: ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hora = $this->appointment->hora_inicio
            ? substr($this->appointment->hora_inicio, 0, 5).' – '.substr($this->appointment->hora_fin ?? '', 0, 5)
            : 'N/D';

        return (new MailMessage)
            ->subject($this->subject)
            ->markdown('emails.message', [
                'accent' => $this->accent,
                'badge' => $this->badge,
                'title' => $this->title,
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => $this->message,
                'rows' => [
                    'Servicio' => $this->appointment->service?->nombre ?? 'N/D',
                    'Barbero' => $this->appointment->barber?->user?->name ?? 'N/D',
                    'Fecha' => optional($this->appointment->fecha)->format('d/m/Y') ?? 'N/D',
                    'Horario' => $hora,
                ],
                'ctaLabel' => $this->actionLabel ?? 'Ver mis citas',
                'ctaUrl' => $this->actionUrl ?? route('client.appointments.index'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment',
            'appointment_id' => $this->appointment->id,
            'subject' => $this->subject,
            'title' => $this->title,
            'message' => $this->message,
            'fecha' => optional($this->appointment->fecha)->toDateString(),
            'hora_inicio' => $this->appointment->hora_inicio,
            'hora_fin' => $this->appointment->hora_fin,
            'url' => $this->actionUrl ?? route('client.appointments.index'),
        ];
    }
}
