<?php

namespace App\Notifications\Channels;

use App\Services\Push\WebPushService;
use Illuminate\Notifications\Notification;

/**
 * Canal de notificacion Web Push, mismo patron que TwilioChannel: si el
 * usuario no tiene ninguna suscripcion o VAPID no esta configurado,
 * WebPushService simula el envio (log) sin romper nada.
 */
class WebPushChannel
{
    public function __construct(private readonly WebPushService $webPush) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);

        if (! is_array($payload) || $payload === []) {
            return;
        }

        $this->webPush->sendToUser((string) $notifiable->id, $payload);
    }
}
