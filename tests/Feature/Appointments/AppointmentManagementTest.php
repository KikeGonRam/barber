<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_admin_can_view_appointments_index(): void
    {
        Appointment::factory()->count(3)->create();

        $response = $this->actingAs($this->admin())->get('/appointments');

        $response->assertOk();
    }

    public function test_admin_can_create_an_appointment(): void
    {
        $client = Client::factory()->create();
        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        $response = $this->actingAs($this->admin())->post('/appointments', [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'hora_fin' => '10:30',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
        ]);
    }

    public function test_store_rejects_overlapping_appointment_for_the_same_barber(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->create(['duracion_min' => 30]);
        $date = now()->addDay()->format('Y-m-d');

        Appointment::factory()->create([
            'barber_id' => (string) $barber->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $response = $this->actingAs($this->admin())->post('/appointments', [
            'client_id' => (string) Client::factory()->create()->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:15',
            'hora_fin' => '10:45',
        ]);

        $response->assertSessionHasErrors('hora_inicio');
    }

    public function test_admin_can_update_appointment_status(): void
    {
        $appointment = Appointment::factory()->create(['estado' => 'pendiente']);

        $response = $this->actingAs($this->admin())
            ->patch(route('appointments.update-status', $appointment), ['estado' => 'confirmada']);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            '_id' => $appointment->id,
            'estado' => 'confirmada',
        ]);
    }

    public function test_update_status_rejects_invalid_status_value(): void
    {
        $appointment = Appointment::factory()->create(['estado' => 'pendiente']);

        $response = $this->actingAs($this->admin())
            ->patch(route('appointments.update-status', $appointment), ['estado' => 'estado-inventado']);

        $response->assertSessionHasErrors('estado');
    }

    public function test_recepcionista_can_register_a_walk_in_appointment(): void
    {
        $recepcionista = User::factory()->create();
        $recepcionista->assignRole('recepcionista');

        $client = Client::factory()->create();
        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true]);

        $response = $this->actingAs($recepcionista)->post('/appointments/walk-in', [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'client_id' => (string) $client->id,
            'estado' => 'en_proceso',
        ]);
    }

    public function test_cliente_cannot_access_appointment_management(): void
    {
        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');

        $this->actingAs($cliente)->get('/appointments')->assertForbidden();
    }

    public function test_guest_is_redirected_from_appointment_management(): void
    {
        $this->get('/appointments')->assertRedirect('/login');
    }
}
