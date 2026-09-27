<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * T053 (HT-19): autenticación por token Bearer. Sin token, con token inventado, vencido o de un
 * usuario que ya no existe responde 401; un token válido de cliente no entra a lo de administración
 * (403); cerrar sesión o renovar el token invalida el anterior.
 */
class ApiTokenSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
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

    /** @return array{0: User, 1: string} */
    private function userWithToken(string $role, ?\DateTimeInterface $expiresAt = null): array
    {
        $roleModel = Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($role).' Seguridad', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $roleModel->id]])->save();

        $plain = 'token-'.Str::random(40);
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt ?? now()->addMonth(),
        ]);

        return [$user, $plain];
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'No autorizado.');
    }

    public function test_an_invented_or_malformed_token_is_rejected(): void
    {
        foreach (['inventado', str_repeat('a', 64), 'Bearer x', "' OR 1=1 --"] as $fake) {
            $this->withToken($fake)->getJson('/api/v1/auth/me')
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Token inválido o expirado.');
        }
    }

    public function test_an_expired_token_is_rejected(): void
    {
        [, $token] = $this->userWithToken('cliente', now()->subMinute());

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token inválido o expirado.');
    }

    public function test_a_token_whose_user_was_deleted_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken('cliente');
        $user->delete();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_valid_token_works_and_records_its_last_use(): void
    {
        [$user, $token] = $this->userWithToken('cliente');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertNotNull(MobileApiToken::where('user_id', (string) $user->id)->first()?->getAttribute('last_used_at'));
    }

    public function test_a_client_token_cannot_reach_staff_or_admin_endpoints(): void
    {
        [, $token] = $this->userWithToken('cliente');

        foreach (['/api/v1/users', '/api/v1/admin/clients', '/api/v1/clients', '/api/v1/settings', '/api/v1/logs', '/api/v1/payments/pending', '/api/v1/reports', '/api/v1/system/backup'] as $uri) {
            $status = $this->withToken($token)->getJson($uri)->getStatusCode();
            $this->assertSame(403, $status, "Un cliente no debe entrar a {$uri} (respondió {$status}).");
        }
    }

    public function test_an_admin_token_can_reach_admin_endpoints(): void
    {
        [, $token] = $this->userWithToken('administrador');

        $this->withToken($token)->getJson('/api/v1/users')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/clients')->assertOk();
    }

    public function test_logout_invalidates_the_token(): void
    {
        [, $token] = $this->userWithToken('cliente');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_refreshing_issues_a_new_token_and_revokes_the_old_one(): void
    {
        [, $old] = $this->userWithToken('cliente');

        $new = $this->withToken($old)->postJson('/api/v1/auth/refresh-token')->assertOk()->json('token');

        $this->assertIsString($new);
        $this->assertNotSame($old, $new);
        $this->withToken($old)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($new)->getJson('/api/v1/auth/me')->assertOk();
    }
}
