<?php

namespace Tests\Feature\Api;

use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class MobileApiBookingTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_client_can_create_and_list_appointments_through_api(): void
    {
        $clientUser = User::factory()->create([
            'name' => 'Cliente Mobile',
            'email' => 'mobile-client@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $clientUser->assignRole('cliente');
        $client = Client::query()->create(['user_id' => $clientUser->id]);

        $barberUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $barberUser->assignRole('barbero');
        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $service = Service::query()->create([
            'nombre' => 'Corte mobile',
            'categoria' => 'corte',
            'precio' => 250,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'mobile-client@example.com',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('token');
        $date = now()->addDay()->toDateString();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/appointments', [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '10:00',
                'notas' => 'Reserva desde mobile',
            ])
            ->assertCreated()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.barber.id', $barber->id)
            ->assertJsonPath('data.service.id', $service->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/appointments')
            ->assertOk()
            ->assertJsonFragment([
                'estado' => 'pendiente',
            ]);
    }
}
