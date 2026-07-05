<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class ClientBookingTest extends TestCase
{
    use RefreshMongoDatabase;

    private function cliente(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');
        Client::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    public function test_client_can_view_own_appointments_index(): void
    {
        $response = $this->actingAs($this->cliente())->get(route('client.appointments.index'));

        $response->assertOk();
    }

    public function test_client_can_book_an_appointment(): void
    {
        $cliente = $this->cliente();
        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        $response = $this->actingAs($cliente)->post(route('client.appointments.store'), [
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '11:00',
        ]);

        $response->assertRedirect(route('client.appointments.index'));
        $this->assertDatabaseHas('appointments', [
            'client_id' => (string) $cliente->clientProfile->id,
            'barber_id' => (string) $barber->id,
        ]);
    }

    public function test_client_cannot_book_two_appointments_the_same_day(): void
    {
        $cliente = $this->cliente();
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);
        $date = now()->addDay()->format('Y-m-d');

        Appointment::factory()->create([
            'client_id' => (string) $cliente->clientProfile->id,
            'fecha' => $date,
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($cliente)->post(route('client.appointments.store'), [
            'barber_id' => (string) Barber::factory()->create(['activo' => true])->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '15:00',
        ]);

        $response->assertSessionHasErrors('fecha');
    }

    public function test_client_can_cancel_an_appointment_with_enough_notice(): void
    {
        $cliente = $this->cliente();
        BarbershopSetting::create(['nombre' => 'Test Shop', 'politica_cancelacion' => 24]);

        $appointment = Appointment::factory()->create([
            'client_id' => (string) $cliente->clientProfile->id,
            'fecha' => now()->addDays(5)->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'estado' => 'pendiente',
        ]);

        $response = $this->actingAs($cliente)->delete(route('client.appointments.destroy', $appointment));

        $response->assertRedirect(route('client.appointments.index'));
        $this->assertDatabaseHas('appointments', [
            '_id' => $appointment->id,
            'estado' => 'cancelada',
        ]);
    }

    public function test_client_cannot_manage_another_clients_appointment(): void
    {
        $cliente = $this->cliente();
        $otherAppointment = Appointment::factory()->create();

        $response = $this->actingAs($cliente)->delete(route('client.appointments.destroy', $otherAppointment));

        $response->assertForbidden();
    }

    public function test_client_can_browse_active_barbers(): void
    {
        Barber::factory()->create(['activo' => true]);

        $response = $this->actingAs($this->cliente())->get(route('client.barberos.index'));

        $response->assertOk();
    }

    public function test_client_can_view_a_barber_profile(): void
    {
        $barber = Barber::factory()->create(['activo' => true]);

        $response = $this->actingAs($this->cliente())->get(route('client.barberos.show', $barber));

        $response->assertOk();
    }

    public function test_client_can_review_a_barber_after_a_completed_appointment(): void
    {
        $cliente = $this->cliente();
        $barber = Barber::factory()->create(['activo' => true]);

        Appointment::factory()->create([
            'client_id' => (string) $cliente->clientProfile->id,
            'barber_id' => (string) $barber->id,
            'estado' => 'completada',
        ]);

        $response = $this->actingAs($cliente)->post(route('client.barberos.review', $barber), [
            'rating' => 5,
            'comment' => 'Excelente servicio.',
        ]);

        $response->assertRedirect();
    }

    public function test_client_cannot_review_a_barber_without_a_completed_appointment(): void
    {
        $cliente = $this->cliente();
        $barber = Barber::factory()->create(['activo' => true]);

        $response = $this->actingAs($cliente)->post(route('client.barberos.review', $barber), [
            'rating' => 5,
            'comment' => 'Excelente servicio.',
        ]);

        $response->assertSessionHasErrors();
    }
}
