<?php

namespace Tests\Feature;

use App\Services\Push\FcmPushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushServiceTest extends TestCase
{
    public function test_unconfigured_fcm_is_a_safe_no_op(): void
    {
        config()->set('services.firebase.project_id', null);
        config()->set('services.firebase.credentials', null);

        $service = new FcmPushService;
        $service->sendToUser((object) [
            'id' => 'user-1',
            'fcm_token' => 'token',
        ], [
            'title' => 'Recordatorio',
            'body' => 'Tu cita comienza pronto.',
        ]);

        $this->assertFalse($service->isConfigured());
    }

    public function test_configured_fcm_sends_android_notification_and_data(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);

        $credentialsPath = tempnam(sys_get_temp_dir(), 'urbanblade-fcm-');
        file_put_contents($credentialsPath, json_encode([
            'client_email' => 'firebase-admin@urbanblade.test',
            'private_key' => $privateKey,
        ], JSON_THROW_ON_ERROR));

        config()->set('services.firebase.project_id', 'urbanblade-test');
        config()->set('services.firebase.credentials', $credentialsPath);
        Cache::forget('firebase:fcm-access-token');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'messages/test']),
        ]);

        try {
            (new FcmPushService)->sendToUser((object) [
                'id' => 'user-1',
                'fcm_token' => 'device-token',
            ], [
                'title' => 'Recordatorio',
                'body' => 'Tu cita comienza pronto.',
                'url' => '/appointments',
            ]);

            Http::assertSent(fn (Request $request): bool => $request->url() === 'https://fcm.googleapis.com/v1/projects/urbanblade-test/messages:send'
                && $request->hasHeader('Authorization', 'Bearer oauth-token')
                && $request['message']['token'] === 'device-token'
                && $request['message']['android']['notification']['channel_id'] === 'citas'
                && $request['message']['data']['url'] === '/appointments'
            );
        } finally {
            @unlink($credentialsPath);
            Cache::forget('firebase:fcm-access-token');
        }
    }
}
