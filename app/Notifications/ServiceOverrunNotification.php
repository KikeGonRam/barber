<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * Aviso al barbero cuando un servicio "en_proceso" supera el tiempo estimado
 * (duracion del servicio) y sigue sin marcarse como completado. Se repite
 * cada 5 minutos via NotifyServiceOverrunCommand hasta que el barbero lo
 * marque como completado. Solo canal in-app: es un aviso operativo urgente,
 * no depende de las preferencias de notificacion del usuario (email/sms).
 */
class ServiceOverrunNotification extends Notification
{
    public function __construct(public readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cliente = $this->appointment->client?->user?->name ?? 'el cliente';
        $servicio = $this->appointment->service?->nombre ?? 'el servicio';

        return [
            'type' => 'service_overrun',
            'appointment_id' => $this->appointment->id,
            'title' => 'Servicio con tiempo excedido',
            'message' => "El servicio de {$cliente} ({$servicio}) ya superó el tiempo estimado. Márcalo como completado cuando termine.",
            'url' => route('barber.agenda'),
        ];
    }
}
