<?php

namespace App\Notifications\Appointment;

use App\Models\Appointment;
use App\Notifications\Concerns\PushesToDevices;
use Illuminate\Notifications\Notification;

/**
 * Aviso al barbero unos minutos antes de que termine el servicio en curso (NotifyServiceEndingCommand): le da tiempo
 * de terminarlo ya o de agregar más tiempo antes de pasarse. Bandeja y push; no manda correo (sería ruido en cada
 * servicio). El texto acompaña a las acciones que muestran las apps: terminar ya / +10 / +15 min.
 */
class ServiceEndingNotification extends Notification
{
    use PushesToDevices;

    public function __construct(public readonly Appointment $appointment, public readonly int $minutesLeft) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', ...$this->pushChannels($notifiable)];
    }

    /**
     * Botones de la notificación en Android: terminar ya o agregar 10/15 min. La app los dibuja y llama a la API;
     * por eso este aviso viaja como «solo datos» (ver FcmPushService), así también salen con la app cerrada.
     *
     * @return array<string, string>
     */
    protected function pushExtras(object $notifiable): array
    {
        return [
            'appointment_code' => (string) $this->appointment->getAttribute('code'),
            'acciones' => 'terminar,extender_10,extender_15',
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $cliente = data_get($this->appointment, 'client.user.name') ?? 'el cliente';
        $servicio = data_get($this->appointment, 'service.nombre') ?? 'el servicio';
        $minutos = max(1, $this->minutesLeft);

        return [
            'type' => 'service_ending',
            'appointment_id' => (string) $this->appointment->id,
            'appointment_code' => $this->appointment->getAttribute('code'),
            'minutes_left' => $minutos,
            'title' => "Tu servicio termina en {$minutos} min",
            'message' => "El servicio de {$cliente} ({$servicio}) está por terminar. ¿Lo terminas ahora o agregas más tiempo?",
            'url' => config('app.frontend_url').'/barber/agenda',
        ];
    }
}
