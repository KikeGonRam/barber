<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Regresión para la
 * migración del dashboard de administrador a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 7 — última rama de
 * dashboard.blade.php) — mismo propósito que las pruebas de los otros 3
 * roles: confirmar que el controlador elige el componente correcto y que
 * el payload (KPIs, agenda de hoy, actividad reciente, estado de barberos)
 * serializa sin romper.
 */
class DashboardAdministradorInertiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_administrador_gets_the_inertia_dashboard_with_expected_props(): void
    {
        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Admin Test', 'email' => 'admin-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Administrador')
            ->has('todayLabel')
            ->has('kpis')
            ->has('incomeChart')
            ->has('servicesChart')
            ->has('barberPerformance')
            ->has('clientTrends')
            ->has('todayAppointments')
            ->has('recentAppointments')
            ->has('sparkHighlights')
            ->where('maintenanceMode', false)
        );
    }

    public function test_a_user_without_a_recognized_role_gets_the_sin_rol_page(): void
    {
        $user = User::create(['name' => 'Sin Rol Test', 'email' => 'sin-rol-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Dashboard/SinRol'));
    }
}
