<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API de preferencias de notificación (fase de notificaciones push): la
 * versión web ya existía (Notification\NotificationController), pero no
 * tenía equivalente Bearer-friendly para Nuxt hasta ahora.
 */
class NotificationPreferencesApiTest extends TestCase
{
    private string $token = 'test-prefs-token';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $this->user = User::create(['name' => 'Cliente Prefs', 'email' => 'cliente-prefs@test.local', 'password' => 'password']);
        $this->user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $this->user->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->token)]);
    }

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_defaults_include_push_disabled(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/notifications/preferences')
            ->assertOk()
            ->assertJsonPath('data.push', false)
            ->assertJsonPath('data.in_app', true)
            ->assertJsonPath('data.email', true);
    }

    public function test_update_merges_instead_of_replacing(): void
    {
        $this->withToken($this->token)
            ->patchJson('/api/v1/notifications/preferences', ['push' => true])
            ->assertOk()
            ->assertJsonPath('data.push', true)
            ->assertJsonPath('data.email', true);

        $this->withToken($this->token)
            ->patchJson('/api/v1/notifications/preferences', ['email' => false])
            ->assertOk()
            ->assertJsonPath('data.email', false)
            ->assertJsonPath('data.push', true);

        $this->assertTrue($this->user->fresh()->wantsNotificationChannel('push'));
        $this->assertFalse($this->user->fresh()->wantsNotificationChannel('email'));
    }
}
