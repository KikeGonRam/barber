<?php

namespace Tests\Feature\Api;

use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class MobileBookingTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_public_services_catalog_only_returns_active_services(): void
    {
        Service::factory()->create(['activo' => true]);
        Service::factory()->create(['activo' => false]);

        $response = $this->getJson('/api/v1/services');

        $response->assertOk();
        $this->assertCount(1, $response->json('data') ?? $response->json());
    }

    public function test_public_barbers_catalog_is_reachable(): void
    {
        Barber::factory()->create(['activo' => true]);

        $this->getJson('/api/v1/barbers')->assertOk();
    }

    public function test_availability_slots_endpoint_requires_valid_parameters(): void
    {
        $response = $this->getJson('/api/v1/availability/slots');

        $response->assertStatus(422);
    }

    public function test_availability_slots_returns_slots_when_a_schedule_exists(): void
    {
        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        $date = now()->addDay();
        if ((int) $date->format('w') === 0) {
            $date = $date->addDay();
        }

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => (int) $date->format('w'),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_working' => true,
        ]);

        $response = $this->getJson('/api/v1/availability/slots?'.http_build_query([
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'date' => $date->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['slots']);
    }

    public function test_authenticated_client_can_create_an_appointment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');
        Client::factory()->create(['user_id' => $user->id]);
        $token = $user->issueMobileApiToken()['token'];

        $barber = Barber::factory()->create(['activo' => true]);
        $service = Service::factory()->create(['activo' => true, 'duracion_min' => 30]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/appointments', [
                'barber_id' => (string) $barber->id,
                'service_id' => (string) $service->id,
                'fecha' => now()->addDay()->format('Y-m-d'),
                'hora_inicio' => '11:00',
            ]);

        $response->assertStatus(201);
    }

    public function test_creating_an_appointment_requires_a_bearer_token(): void
    {
        $this->postJson('/api/v1/appointments', [])->assertStatus(401);
    }

    public function test_client_can_list_their_own_appointments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');
        Client::factory()->create(['user_id' => $user->id]);
        $token = $user->issueMobileApiToken()['token'];

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/appointments');

        $response->assertOk();
    }
}
