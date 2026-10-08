<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Suscribir el navegador a Web Push enciende el canal «push» solo si la persona nunca eligió. */
class WebPushSubscribeTest extends TestCase
{
    protected function tearDown(): void
    {
        PushSubscription::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    /** @return array{0: User, 1: string} */
    private function user(?array $preferences): array
    {
        $user = User::create(['name' => 'Web', 'email' => Str::uuid().'@test.local', 'password' => 'password', 'notification_preferences' => $preferences]);
        $plain = 'token-'.Str::random(40);
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);

        return [$user, $plain];
    }

    private function payload(): array
    {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::random(20), 'keys' => ['p256dh' => 'BKey', 'auth' => 'authKey']];
    }

    public function test_subscribing_turns_push_on_when_the_user_never_chose(): void
    {
        [$user, $token] = $this->user(null);

        $this->withToken($token)->postJson('/api/v1/push/subscribe', $this->payload())->assertCreated();

        $this->assertTrue($user->fresh()->wantsNotificationChannel('push'));
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_subscribing_keeps_the_choice_of_a_user_who_turned_push_off(): void
    {
        [$user, $token] = $this->user(['push' => false, 'email' => true]);

        $this->withToken($token)->postJson('/api/v1/push/subscribe', $this->payload())->assertCreated();

        $this->assertFalse($user->fresh()->wantsNotificationChannel('push'));
    }

    public function test_subscribing_keeps_other_preferences(): void
    {
        [$user, $token] = $this->user(['email' => false]);

        $this->withToken($token)->postJson('/api/v1/push/subscribe', $this->payload())->assertCreated();

        $prefs = $user->fresh()->notificationPreferences();
        $this->assertTrue($prefs['push']);
        $this->assertFalse($prefs['email']);
    }
}
