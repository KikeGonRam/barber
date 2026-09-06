<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API de suscripciones Web Push (fase de notificaciones push para citas
 * próximas): antes no existía ninguna API para esto — no había
 * infraestructura de push en ningún lado del proyecto.
 */
class PushApiTest extends TestCase
{
    private string $token = 'test-push-token';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $this->user = User::create(['name' => 'Cliente Push', 'email' => 'cliente-push@test.local', 'password' => 'password']);
        $this->user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $this->user->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->token)]);
    }

    protected function tearDown(): void
    {
        PushSubscription::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_guest_cannot_reach_push_endpoints(): void
    {
        $this->getJson('/api/v1/push/vapid-public-key')->assertStatus(401);
        $this->postJson('/api/v1/push/subscribe', [])->assertStatus(401);
    }

    public function test_vapid_public_key_matches_config(): void
    {
        config(['services.vapid.public_key' => 'test-public-key']);

        $this->withToken($this->token)
            ->getJson('/api/v1/push/vapid-public-key')
            ->assertOk()
            ->assertJsonPath('public_key', 'test-public-key');
    }

    public function test_subscribe_creates_a_subscription_tied_to_the_user(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc123',
            'keys' => ['p256dh' => 'fake-p256dh', 'auth' => 'fake-auth'],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => (string) $this->user->id,
            'endpoint' => 'https://push.example.com/abc123',
            'public_key' => 'fake-p256dh',
            'auth_token' => 'fake-auth',
            'content_encoding' => 'aes128gcm',
        ]);
    }

    public function test_subscribing_the_same_endpoint_twice_updates_instead_of_duplicating(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/same',
            'keys' => ['p256dh' => 'old-key', 'auth' => 'old-auth'],
        ])->assertStatus(201);

        $this->withToken($this->token)->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/same',
            'keys' => ['p256dh' => 'new-key', 'auth' => 'new-auth'],
        ])->assertStatus(201);

        $this->assertSame(1, PushSubscription::where('endpoint', 'https://push.example.com/same')->count());
        $this->assertSame('new-key', PushSubscription::where('endpoint', 'https://push.example.com/same')->first()->public_key);
    }

    public function test_unsubscribe_removes_only_the_matching_endpoint(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/keep',
            'keys' => ['p256dh' => 'k1', 'auth' => 'a1'],
        ]);
        $this->withToken($this->token)->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/remove',
            'keys' => ['p256dh' => 'k2', 'auth' => 'a2'],
        ]);

        $this->withToken($this->token)->deleteJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/remove',
        ])->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://push.example.com/remove']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://push.example.com/keep']);
    }

    public function test_subscribe_validates_required_fields(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/push/subscribe', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint', 'keys']);
    }
}
