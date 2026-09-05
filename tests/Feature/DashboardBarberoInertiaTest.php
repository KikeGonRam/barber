<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Regresión para la
 * migración del dashboard de barbero a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 5) — mismo propósito
 * que DashboardRecepcionInertiaTest: confirmar que el controlador elige el
 * componente correcto y que el payload serializa sin romper (relaciones de
 * Appointment con client.user/service, y el campo calculado `isNext`).
 */
class DashboardBarberoInertiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_barbero_gets_the_inertia_dashboard_with_expected_props(): void
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Barbero Test', 'email' => 'barbero-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Test', 'activo' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Barbero')
            ->has('todayLabel')
            ->has('kpis')
            ->has('performanceChart')
            ->has('servicesChart')
            ->has('barberToday')
            ->has('barberPending')
            ->has('sparkHighlights')
        );
    }
}
