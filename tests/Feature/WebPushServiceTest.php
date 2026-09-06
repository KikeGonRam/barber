<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Services\Push\WebPushService;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

/**
 * Cubre los caminos "sin romper nada" de WebPushService (fase de
 * notificaciones push): sin suscripciones, o sin VAPID configurado, nunca
 * debe intentar una llamada de red real ni lanzar excepción — mismo
 * criterio que MessagingService (Twilio) ya usa para SMS/WhatsApp.
 */
class WebPushServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        PushSubscription::query()->delete();

        parent::tearDown();
    }

    public function test_is_configured_reflects_vapid_keys(): void
    {
        config(['services.vapid.public_key' => null, 'services.vapid.private_key' => null]);
        $this->assertFalse((new WebPushService)->isConfigured());

        config(['services.vapid.public_key' => 'pub', 'services.vapid.private_key' => 'priv']);
        $this->assertTrue((new WebPushService)->isConfigured());
    }

    public function test_send_to_user_is_a_noop_without_subscriptions(): void
    {
        config(['services.vapid.public_key' => 'pub', 'services.vapid.private_key' => 'priv']);

        (new WebPushService)->sendToUser('nonexistent-user-id', ['title' => 'x', 'body' => 'y']);

        $this->addToAssertionCount(1);
    }

    public function test_send_to_user_simulates_when_vapid_not_configured(): void
    {
        config(['services.vapid.public_key' => null, 'services.vapid.private_key' => null]);

        PushSubscription::create([
            'user_id' => 'fake-user-id',
            'endpoint' => 'https://push.example.com/x',
            'public_key' => 'k',
            'auth_token' => 'a',
            'content_encoding' => 'aes128gcm',
        ]);

        (new WebPushService)->sendToUser('fake-user-id', ['title' => 'x', 'body' => 'y']);

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => 'fake-user-id']);
    }

    /**
     * Regresión: encontrada en vivo (2026-09-06) probando la Fase 2 del push
     * -- una suscripción con una clave mal formada hacía que
     * WebPush::sendOneNotification()/flush() lanzara una excepción no
     * capturada ("Invalid data: only uncompressed keys are supported"). La
     * primera versión de este método usaba queueNotification()+flush(), y
     * flush() devuelve un Generator que valida/cifra cada suscripción de
     * forma perezosa: la excepción de UNA suscripción mataba el generador
     * completo, así que ninguna suscripción posterior en el mismo lote
     * llegaba a enviarse. Mandar de a una con sendOneNotification(), cada
     * una en su propio try/catch, aísla el fallo.
     */
    public function test_send_to_user_does_not_throw_and_prunes_a_malformed_subscription(): void
    {
        $keys = VAPID::createVapidKeys();
        config(['services.vapid.public_key' => $keys['publicKey'], 'services.vapid.private_key' => $keys['privateKey']]);

        PushSubscription::create([
            'user_id' => 'fake-user-id',
            'endpoint' => 'https://push.example.com/malformed',
            'public_key' => 'not-a-valid-p256-key',
            'auth_token' => 'a',
            'content_encoding' => 'aes128gcm',
        ]);

        (new WebPushService)->sendToUser('fake-user-id', ['title' => 'x', 'body' => 'y']);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/malformed']);
    }
}
