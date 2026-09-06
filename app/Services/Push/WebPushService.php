<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envía notificaciones Web Push (VAPID) a las suscripciones de un usuario.
 * Si las claves VAPID no están configuradas, simula el envío con un log
 * info en vez de romper — mismo criterio que MessagingService (Twilio) para
 * no exigir credenciales reales en desarrollo.
 */
class WebPushService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.vapid.public_key') && (bool) config('services.vapid.private_key');
    }

    /**
     * Manda el mismo payload a todas las suscripciones del usuario. Borra
     * cualquier suscripción que el navegador reporte como expirada/inválida
     * (404/410) para que el próximo envío no la vuelva a intentar.
     */
    public function sendToUser(string $userId, array $payload): void
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        if (! $this->isConfigured()) {
            Log::info('Push simulado (VAPID no configurado)', ['user_id' => $userId, 'payload' => $payload]);

            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);

        $json = json_encode($payload);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, $subscription->content_encoding),
                $json,
            );
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }
}
