<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_recepcionista_without_citas_permission_cannot_access_appointments(): void
    {
        $user = $this->makeVerifiedUserWithRole('recepcionista');
        $role = Role::query()->where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('citas.gestionar');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertForbidden();
    }

    public function test_recepcionista_without_pagos_permission_cannot_access_payments(): void
    {
        $user = $this->makeVerifiedUserWithRole('recepcionista');
        $role = Role::query()->where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('pagos.gestionar');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('payments.index'))
            ->assertForbidden();
    }

    public function test_recepcionista_without_inventory_permissions_cannot_access_movements(): void
    {
        $user = $this->makeVerifiedUserWithRole('recepcionista');
        $role = Role::query()->where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('inventario.ver');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('inventory.movements.index'))
            ->assertForbidden();
    }

    public function test_recepcionista_without_clientes_permission_cannot_access_clients(): void
    {
        $user = $this->makeVerifiedUserWithRole('recepcionista');
        $role = Role::query()->where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('clientes.gestionar');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertForbidden();
    }

    public function test_administrador_without_reportes_permission_cannot_access_reports(): void
    {
        $user = $this->makeVerifiedUserWithRole('administrador');
        $role = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('reportes.ver');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_administrador_without_usuarios_permission_cannot_access_users(): void
    {
        $user = $this->makeVerifiedUserWithRole('administrador');
        $role = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('usuarios.gestionar');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_administrador_without_barberos_permission_cannot_access_barbers(): void
    {
        $user = $this->makeVerifiedUserWithRole('administrador');
        $role = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('barberos.gestionar');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('barbers.index'))
            ->assertForbidden();
    }

    public function test_administrador_without_configuracion_permission_cannot_access_settings(): void
    {
        $user = $this->makeVerifiedUserWithRole('administrador');
        $role = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('configuracion.gestionar');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertForbidden();
    }

    public function test_administrador_without_logs_permission_cannot_access_logs(): void
    {
        $user = $this->makeVerifiedUserWithRole('administrador');
        $role = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();

        $role->revokePermissionTo('logs.ver');
        $this->refreshPermissionCache();

        $this->actingAs($user)
            ->get(route('logs.index'))
            ->assertForbidden();
    }

    private function makeVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->firstOrFail();

        $user->assignRole($role);

        return $user;
    }

    private function refreshPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
