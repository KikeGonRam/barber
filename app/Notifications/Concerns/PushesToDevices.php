<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\FcmPushChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Push\PushRouting;

/**
 * Lleva una notificación al navegador (Web Push) y al teléfono (FCM) usando el mismo contenido que
 * ya se guarda en la bandeja (toArray): título, mensaje y enlace. Solo se envía si el usuario
 * activó el canal "push" en sus preferencias.
 */
trait PushesToDevices
{
    /** @return array<int, class-string> */
    protected function pushChannels(object $notifiable): array
    {
        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('push')) {
            return [WebPushChannel::class, FcmPushChannel::class];
        }

        return [];
    }

    /** @return array<string, string> */
    public function toWebPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $type = (string) ($data['type'] ?? 'general');
        [$channel, $route] = PushRouting::for($type);

        return [
            'title' => (string) ($data['title'] ?? 'UrbanBlade'),
            'body' => (string) ($data['message'] ?? ''),
            'url' => (string) ($data['url'] ?? config('app.frontend_url')),
            'type' => $type,
            'channel' => $channel,
            'route' => $route,
        ];
    }
}
