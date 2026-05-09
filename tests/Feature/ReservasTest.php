<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservasTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $barber;
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->barber = Barber::factory()->create(['user_id' => $this->user->id]);
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
    }

    // ==================== TESTS DE API ENDPOINTS ====================

    public function test_obtener_reservas_lista_todas(): void
    {
        $appointments = Appointment::factory()
            ->count(5)
            ->create(['barber_id' => $this->barber->id, 'client_id' => $this->client->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reservas');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['*' => ['id', 'barber_id', 'client_id', 'fecha', 'hora']]])
            ->assertJsonCount(5, 'data');
    }

    public function test_obtener_reserva_por_id(): void
    {
        $appointment = Appointment::factory()
            ->create(['barber_id' => $this->barber->id, 'client_id' => $this->client->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/reservas/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.barber_id', $this->barber->id);
    }

    public function test_crear_reserva_nueva(): void
    {
        $data = [
            'barber_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'fecha' => '2026-05-20',
            'hora' => '10:00',
            'servicio' => 'Corte + Barba',
            'duracion' => 60,
            'notas' => 'Cliente especial',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reservas', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.fecha', '2026-05-20')
            ->assertJsonPath('data.hora', '10:00');

        $this->assertDatabaseHas('appointments', [
            'barber_id' => $this->barber->id,
            'servicio' => 'Corte + Barba',
        ]);
    }

    public function test_crear_reserva_sin_barbero_falla(): void
    {
        $data = [
            'client_id' => $this->client->id,
            'fecha' => '2026-05-20',
            'hora' => '10:00',
            // Sin barber_id
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reservas', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['barber_id']);
    }

    public function test_actualizar_reserva(): void
    {
        $appointment = Appointment::factory()
            ->create(['barber_id' => $this->barber->id, 'client_id' => $this->client->id]);

        $data = ['hora' => '14:00', 'servicio' => 'Solo Corte'];

        $response = $this->actingAs($this->user)
            ->putJson("/api/reservas/{$appointment->id}", $data);

        $response->assertStatus(200)
            ->assertJsonPath('data.hora', '14:00');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'hora' => '14:00',
        ]);
    }

    public function test_cancelar_reserva(): void
    {
        $appointment = Appointment::factory()
            ->create(['barber_id' => $this->barber->id, 'client_id' => $this->client->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/reservas/{$appointment->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }

    // ==================== TESTS DE BÚSQUEDA ====================

    public function test_buscar_reservas_por_cliente(): void
    {
        Appointment::factory()
            ->count(3)
            ->create(['client_id' => $this->client->id, 'barber_id' => $this->barber->id]);

        Appointment::factory()
            ->count(2)
            ->create(['barber_id' => $this->barber->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reservas?client_id=' . $this->client->id);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_buscar_reservas_por_fecha(): void
    {
        Appointment::factory()
            ->create([
                'barber_id' => $this->barber->id,
                'client_id' => $this->client->id,
                'fecha' => '2026-05-15',
            ]);

        Appointment::factory()
            ->create([
                'barber_id' => $this->barber->id,
                'client_id' => $this->client->id,
                'fecha' => '2026-05-20',
            ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/reservas?fecha=2026-05-15');

        $response->assertStatus(200);
        // Validar que contiene la reserva correcta
    }

    // ==================== TESTS DE VALIDACIÓN ====================

    public function test_reserva_con_hora_pasada_falla(): void
    {
        $data = [
            'barber_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'fecha' => '2026-05-01',  // Fecha pasada
            'hora' => '08:00',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reservas', $data);

        // Debería validar fecha en el futuro
        $response->assertStatus(422);
    }

    public function test_no_puede_agendar_sin_autenticacion(): void
    {
        $data = [
            'barber_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'fecha' => '2026-05-20',
            'hora' => '10:00',
        ];

        $response = $this->postJson('/api/reservas', $data);

        $response->assertStatus(401);  // Unauthorized
    }

    // ==================== TESTS DE CONFLICTOS ====================

    public function test_no_puede_agendar_dos_clientes_misma_hora_barbero(): void
    {
        $appointment1 = Appointment::factory()
            ->create([
                'barber_id' => $this->barber->id,
                'fecha' => '2026-05-20',
                'hora' => '10:00',
            ]);

        $data = [
            'barber_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'fecha' => '2026-05-20',
            'hora' => '10:00',  // Misma hora
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reservas', $data);

        // Debería fallar por conflicto
        $response->assertStatus(409);  // Conflict
    }

    public function test_puede_agendar_distinto_barbero_misma_hora(): void
    {
        $barber2 = Barber::factory()->create(['user_id' => $this->user->id]);

        $appointment1 = Appointment::factory()
            ->create([
                'barber_id' => $this->barber->id,
                'fecha' => '2026-05-20',
                'hora' => '10:00',
            ]);

        $data = [
            'barber_id' => $barber2->id,  // Barbero diferente
            'client_id' => $this->client->id,
            'fecha' => '2026-05-20',
            'hora' => '10:00',  // Misma hora, diferente barbero
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/reservas', $data);

        $response->assertStatus(201);  // Debe ser exitoso
    }
}
