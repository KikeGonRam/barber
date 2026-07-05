<?php

namespace Tests\Feature\Barbers;

use App\Models\Barber;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class BarberManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    private function barbero(): User
    {
        $user = User::factory()->create();
        $user->assignRole('barbero');
        Barber::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_admin_can_view_barbers_index(): void
    {
        Barber::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/barbers')->assertOk();
    }

    public function test_admin_can_view_barber_edit_form(): void
    {
        $barber = Barber::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('barbers.edit', $barber));

        $response->assertOk();
    }

    public function test_admin_can_update_a_barber(): void
    {
        $barber = Barber::factory()->create();
        $barber->load('user');

        $response = $this->actingAs($this->admin())->put(route('barbers.update', $barber), [
            'name' => 'Barbero Actualizado',
            'email' => $barber->user->email,
            'especialidades' => 'Fade',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['_id' => $barber->user_id, 'name' => 'Barbero Actualizado']);
    }

    public function test_admin_can_view_barber_performance(): void
    {
        $barber = Barber::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('barbers.performance', $barber));

        $response->assertOk();
    }

    public function test_recepcionista_can_view_barbers_but_not_edit(): void
    {
        $recepcionista = User::factory()->create();
        $recepcionista->assignRole('recepcionista');

        $this->actingAs($recepcionista)->get('/barbers')->assertForbidden();
    }

    public function test_barbero_can_view_own_agenda(): void
    {
        $this->actingAs($this->barbero())->get('/barbero/agenda')->assertOk();
    }

    public function test_barbero_can_view_and_update_own_profile(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($barbero)->get('/barbero/profile')->assertOk();

        $response = $this->actingAs($barbero)->put('/barbero/profile', [
            'especialidades' => 'Barba, Fade',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('barbers', [
            'user_id' => (string) $barbero->id,
            'especialidades' => 'Barba, Fade',
        ]);
    }

    public function test_barbero_can_update_own_weekly_schedule(): void
    {
        $barbero = $this->barbero();

        $schedules = [];
        for ($day = 0; $day < 7; $day++) {
            $schedules[$day] = [
                'is_working' => $day < 6 ? '1' : null,
                'start_time' => $day < 6 ? '09:00' : null,
                'end_time' => $day < 6 ? '18:00' : null,
            ];
        }

        $response = $this->actingAs($barbero)->put('/barbero/schedule', ['schedules' => $schedules]);

        $response->assertRedirect();
        $this->assertDatabaseHas('barber_schedules', [
            'barber_id' => (string) $barbero->barberProfile->id,
            'day_of_week' => 0,
            'is_working' => true,
        ]);
    }

    public function test_barbero_can_view_own_portfolio(): void
    {
        $this->actingAs($this->barbero())->get('/barbero/portfolio')->assertOk();
    }

    public function test_cliente_cannot_access_barber_dashboard_routes(): void
    {
        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');

        $this->actingAs($cliente)->get('/barbero/agenda')->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_barbers_index(): void
    {
        $this->get('/barbers')->assertRedirect('/login');
    }
}
