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
            ? substr($this->appointment->hora_inicio, 0, 5).' — '.substr($this->appointment->hora_fin ?? '', 0, 5)
            : 'N/D';

        return (new MailMessage)
            ->subject($this->subject)
            ->greeting('Hola '.$notifiable->name.',')
            ->line($this->title)
            ->line($this->message)
            ->line('**Fecha:** '.optional($this->appointment->fecha)->format('d/m/Y'))
            ->line('**Horario:** '.$hora)
            ->line('**Servicio:** '.($this->appointment->service?->nombre ?? 'N/D'))
            ->action(
                $this->actionLabel ?? 'Ver mis citas',
                $this->actionUrl ?? route('client.appointments.index'),
            );
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
