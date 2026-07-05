<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class RouteGuardAccessTest extends TestCase
{
    use RefreshMongoDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_is_redirected_away_from_admin_users_index(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect('/login');
    }

    public function test_cliente_cannot_access_admin_users_management(): void
    {
        $cliente = $this->userWithRole('cliente');

        $response = $this->actingAs($cliente)->get('/users');

        $response->assertForbidden();
    }

    public function test_administrador_can_access_admin_users_management(): void
    {
        $admin = $this->userWithRole('administrador');

        $response = $this->actingAs($admin)->get('/users');

        $response->assertOk();
    }

    public function test_barbero_cannot_access_client_management(): void
    {
        $barbero = $this->userWithRole('barbero');

        $response = $this->actingAs($barbero)->get('/clients');

        $response->assertForbidden();
    }

    public function test_recepcionista_can_access_client_management(): void
    {
        $recepcionista = $this->userWithRole('recepcionista');

        $response = $this->actingAs($recepcionista)->get('/clients');

        $response->assertOk();
    }

    public function test_cliente_cannot_access_barber_agenda(): void
    {
        $cliente = $this->userWithRole('cliente');

        $response = $this->actingAs($cliente)->get('/barbero/agenda');

        $response->assertForbidden();
    }

    public function test_barbero_can_access_own_agenda(): void
    {
        $barbero = $this->userWithRole('barbero');
        \App\Models\Barber::factory()->create(['user_id' => $barbero->id]);

        $response = $this->actingAs($barbero)->get('/barbero/agenda');

        $response->assertOk();
    }

    public function test_only_administrador_can_manage_services(): void
    {
        $recepcionista = $this->userWithRole('recepcionista');
        $admin = $this->userWithRole('administrador');

        $this->actingAs($recepcionista)->get('/services')->assertForbidden();
        $this->actingAs($admin)->get('/services')->assertOk();
    }

    public function test_only_administrador_can_view_activity_logs(): void
    {
        $recepcionista = $this->userWithRole('recepcionista');
        $admin = $this->userWithRole('administrador');

        $this->actingAs($recepcionista)->get('/logs')->assertForbidden();
        $this->actingAs($admin)->get('/logs')->assertOk();
    }

    public function test_dashboard_is_reachable_by_every_role(): void
    {
        foreach (['administrador', 'recepcionista', 'barbero', 'cliente'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get('/dashboard')->assertOk();
        }
    }
}
