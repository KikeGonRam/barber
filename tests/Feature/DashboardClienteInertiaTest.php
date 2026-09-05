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
 * migración del dashboard de cliente a Inertia+Vue (ver
 * .claude/skills/inertia-vue-migration/SKILL.md, Fase 6) — mismo propósito
 * que las pruebas de Recepción/Barbero: confirmar que el controlador elige
 * el componente correcto y que el payload (lealtad, tarjeta de membresía
 * con QR generado, próxima cita) serializa sin romper.
 */
class DashboardClienteInertiaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cliente_gets_the_inertia_dashboard_with_expected_props(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Test', 'email' => 'cliente-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Cliente')
            ->has('todayLabel')
            ->has('kpis')
            ->has('visitChart')
            ->has('loyalty')
            ->has('loyalty.nivel')
            ->has('loyalty.nivelLabel')
            ->has('member')
            ->has('member.number')
            ->has('member.qr')
            ->where('nextAppointment', null)
            ->where('recommendation', null)
        );
    }
}
