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
 * GET /api/v1/system/backup: paridad con backups.database.download (Blade,
 * ver App\Services\System\DatabaseBackupService) via token Bearer para el
 * botón de frontend-urban. Solo administrador.
 */
class DatabaseBackupApiTest extends TestCase
{
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

    public function test_admin_can_download_a_backup_zip(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->userWithRole('administrador', 'admin-backup@test.local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->get('/api/v1/system/backup');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
    }

    public function test_non_admin_cannot_download_a_backup(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $recepcionista = $this->userWithRole('recepcionista', 'recepcion-backup@test.local');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($recepcionista))
            ->get('/api/v1/system/backup');

        $response->assertForbidden();
    }

    public function test_guest_cannot_download_a_backup(): void
    {
        $response = $this->get('/api/v1/system/backup');

        $response->assertUnauthorized();
    }
}
