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

        $webPush = new WebPush(
            auth: [
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ],
            ],
            // Sin esto, la librería usa trigger_error() para su aviso de
            // rendimiento ("instala GMP o BCMath") -- un E_USER_NOTICE que
            // PHPUnit convierte en fallo de test y que en producción
            // ensuciaría el log de errores de PHP en vez de los logs de la
            // app. Con un logger PSR-3 inyectado, usa $logger->notice() en
            // su lugar.
            logger: Log::getFacadeRoot(),
        );

        $json = json_encode($payload);

        // Uno por uno (sendOneNotification), no queueNotification()+flush():
        // flush() devuelve un Generator que valida/cifra cada suscripción de
        // forma perezosa mientras se itera, así que una sola suscripción
        // corrupta (p. ej. una clave mal formada) lanza una excepción que
        // mata el generador completo — ninguna suscripción posterior en el
        // lote se llega a enviar. Mandarlas de una en una, cada una en su
        // propio try/catch, aísla ese fallo sin bloquear al resto.
        foreach ($subscriptions as $subscription) {
            try {
                $report = $webPush->sendOneNotification(
                    new Subscription($subscription->endpoint, $subscription->public_key, $subscription->auth_token, $subscription->content_encoding),
                    $json,
                );

                if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                    $subscription->delete();
                }
            } catch (\Throwable $e) {
                Log::warning('Suscripción push inválida, se omite y se elimina', ['endpoint' => $subscription->endpoint, 'error' => $e->getMessage()]);
                $subscription->delete();
            }
        }
    }
}
