<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_notification_preferences_returns_defaults_when_no_client_profile(): void
    {
        $user = User::factory()->create();

        $prefs = $user->notificationPreferences();

        $this->assertTrue($prefs['in_app']);
        $this->assertTrue($prefs['email']);
        $this->assertFalse($prefs['sms']);
        $this->assertFalse($prefs['whatsapp']);
    }

    public function test_notification_preferences_merges_client_profile_preferences(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => [
                'in_app' => false,
                'email' => true,
                'sms' => true,
                'whatsapp' => false,
            ],
        ]);

        $prefs = $user->notificationPreferences();

        $this->assertFalse($prefs['in_app']);
        $this->assertTrue($prefs['email']);
        $this->assertTrue($prefs['sms']);
        $this->assertFalse($prefs['whatsapp']);
    }

    public function test_notification_preferences_returns_defaults_when_client_preferences_are_not_array(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => null,
        ]);

        $prefs = $user->notificationPreferences();

        $this->assertTrue($prefs['in_app']);
        $this->assertTrue($prefs['email']);
    }

    public function test_wants_notification_channel_returns_true_for_enabled_channel(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => true, 'sms' => false, 'whatsapp' => false],
        ]);

        $this->assertTrue($user->wantsNotificationChannel('email'));
        $this->assertTrue($user->wantsNotificationChannel('in_app'));
    }

    public function test_wants_notification_channel_returns_false_for_disabled_channel(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => true, 'sms' => false, 'whatsapp' => false],
        ]);

        $this->assertFalse($user->wantsNotificationChannel('sms'));
        $this->assertFalse($user->wantsNotificationChannel('whatsapp'));
    }

    public function test_wants_notification_channel_returns_false_for_unknown_channel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->wantsNotificationChannel('push'));
    }

    public function test_client_phone_returns_null_when_no_client_profile(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->clientPhone());
    }

    public function test_client_phone_returns_phone_from_client_profile(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'telefono' => '+521234567890',
        ]);

        $this->assertSame('+521234567890', $user->clientPhone());
    }

    public function test_issue_mobile_api_token_creates_token_record(): void
    {
        $user = User::factory()->create();

        ['token' => $plainToken, 'token_model' => $tokenModel] = $user->issueMobileApiToken('Test App');

        $this->assertNotEmpty($plainToken);
        $this->assertInstanceOf(MobileApiToken::class, $tokenModel);
        $this->assertSame('Test App', $tokenModel->name);
        $this->assertSame(hash('sha256', $plainToken), $tokenModel->token_hash);
        $this->assertDatabaseHas('mobile_api_tokens', [
            'user_id' => $user->id,
            'name' => 'Test App',
        ]);
    }

    public function test_issue_mobile_api_token_with_abilities_and_expiry(): void
    {
        $user = User::factory()->create();
        $expiresAt = now()->addDays(30);

        ['token' => $plainToken, 'token_model' => $tokenModel] = $user->issueMobileApiToken(
            'Mobile App',
            ['read', 'write'],
            $expiresAt,
        );

        $this->assertSame(['read', 'write'], $tokenModel->abilities);
        $this->assertTrue($tokenModel->expires_at->isSameDay($expiresAt));
    }

    public function test_issue_mobile_api_token_returns_unique_plain_tokens(): void
    {
        $user = User::factory()->create();

        ['token' => $token1] = $user->issueMobileApiToken();
        ['token' => $token2] = $user->issueMobileApiToken();

        $this->assertNotSame($token1, $token2);
    }
}
