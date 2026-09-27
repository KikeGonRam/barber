<?php

namespace App\Notifications\Channels;

use App\Services\Push\FcmPushService;
use Illuminate\Notifications\Notification;

/** Entrega el mismo payload de cita a la aplicación Android mediante FCM. */
class FcmPushChannel
{
    public function __construct(private readonly FcmPushService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if (is_array($payload) && $payload !== []) {
            $this->fcm->sendToUser($notifiable, $payload);
        }
    }
}
