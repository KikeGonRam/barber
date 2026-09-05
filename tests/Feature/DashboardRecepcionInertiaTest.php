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
 * migración del dashboard de recepcionista a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 4): antes de esta
 * fase nada probaba /dashboard para ningún rol, así que este test cubre
 * tanto que el controlador elige el componente Inertia correcto como que
 * el payload no rompe al serializar relaciones de MongoDB (Appointment/
 * Order con client.user, barber.user, service).
 */
class DashboardRecepcionInertiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_recepcionista_gets_the_inertia_dashboard_with_expected_props(): void
    {
        $role = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Recepcionista Test', 'email' => 'recepcion-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Recepcion')
            ->has('kpis')
            ->has('nextAppointments')
            ->has('pendingOrders')
            ->has('flowChart')
            ->has('sparkHighlights')
        );
    }
}
