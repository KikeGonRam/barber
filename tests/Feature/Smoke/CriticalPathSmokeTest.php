<?php

namespace Tests\Feature\Smoke;

/**
 * Cubre pruebas de humo de rutas criticas para validar que el sistema responde
 * en los caminos principales (200, redirects esperados y 404).
 */

use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CriticalPathSmokeTest extends TestCase
{
    use RefreshMongoDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: Rutas publicas ---

    public function test_get_login_returns_200(): void
    {
        $this->get(route('login'))
            ->assertOk();
    }

    public function test_get_register_returns_200(): void
    {
        $this->get(route('register'))
            ->assertOk();
    }

    // --- Contexto: Dashboard ---

    public function test_dashboard_redirects_to_login_for_guest(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_returns_200_for_authenticated_verified_user(): void
    {
        $user = $this->makeVerifiedUserWithRole('recepcionista');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    // --- Contexto: Portales por rol ---

    public function test_appointments_index_returns_200_for_recepcionista(): void
    {
        $recepcionista = $this->makeVerifiedUserWithRole('recepcionista');

        $this->actingAs($recepcionista)
            ->get(route('appointments.index'))
            ->assertOk();
    }

    public function test_reports_index_returns_200_for_administrador(): void
    {
        $admin = $this->makeVerifiedUserWithRole('administrador');

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_barber_agenda_returns_200_for_barbero(): void
    {
        $barbero = $this->makeVerifiedUserWithRole('barbero');

        Barber::query()->create([
            'user_id' => $barbero->id,
            'activo' => true,
        ]);

        $this->actingAs($barbero)
            ->get(route('barber.agenda'))
            ->assertOk();
    }

    public function test_client_appointments_returns_200_for_cliente(): void
    {
        $cliente = $this->makeVerifiedUserWithRole('cliente');

        Client::query()->create([
            'user_id' => $cliente->id,
            'preferencias_notificacion' => [
                'in_app' => true,
                'email' => true,
            ],
        ]);

        $this->actingAs($cliente)
            ->get(route('client.appointments.index'))
            ->assertOk();
    }

    // --- Contexto: Manejo de rutas inexistentes ---

    public function test_non_existing_route_returns_404(): void
    {
        $this->get('/__no-existe-ruta-critica-smoke__')
            ->assertNotFound();
    }

    private function makeVerifiedUserWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
