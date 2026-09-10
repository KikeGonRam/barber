<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * GET /api/v1/dashboard/membership/card: paridad con client.membership.card
 * (Blade, ver App\Services\Member\MemberCardService::cardPdfData()) via
 * token Bearer para el botón "Descargar tarjeta" de MembershipCard.vue.
 * Solo cliente.
 */
class MembershipCardApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(User $user): string
    {
        $plain = 'test-token-'.$user->id;
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plain),
        ]);

        return $plain;
    }

    private function userWithRole(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    public function test_client_can_download_their_membership_card_pdf(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $cliente = $this->userWithRole('cliente', 'cliente-card@test.local');
        Client::create(['user_id' => (string) $cliente->id, 'nivel' => 'vip', 'puntos' => 250]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($cliente))
            ->get('/api/v1/dashboard/membership/card');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_non_client_cannot_download_a_membership_card(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('administrador', 'admin-card@test.local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->get('/api/v1/dashboard/membership/card');

        $response->assertForbidden();
    }

    public function test_guest_cannot_download_a_membership_card(): void
    {
        $response = $this->get('/api/v1/dashboard/membership/card');

        $response->assertUnauthorized();
    }
}
