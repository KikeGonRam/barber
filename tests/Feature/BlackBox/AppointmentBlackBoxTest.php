<?php

namespace Tests\Feature\BlackBox;

/**
 * Cubre pruebas de caja negra para creacion de citas: valida entradas
 * y salidas esperadas sin depender de implementacion interna.
 */

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentBlackBoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        ]);

        BarbershopSetting::query()->create([
            'nombre' => 'BarberPro Elite',
            'maintenance_mode' => false,
        ]);
    }

    // --- Contexto: creacion valida ---

    public function test_create_appointment_with_valid_data_redirects_and_persists_record(): void
    {
        [$actor, $client, $barber, $service] = $this->bootstrapScenario();

        $date = now()->addDays(2)->toDateString();

        $this->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
                'estado' => 'confirmada',
            ])
            ->assertRedirect(route('appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
        ]);
    }

    // --- Contexto: validaciones de entrada ---

    public function test_create_appointment_with_invalid_start_time_format_returns_validation_error(): void
    {
        [$actor, $client, $barber, $service] = $this->bootstrapScenario();

        $date = now()->addDays(2)->toDateString();

        $this->from(route('appointments.create'))
            ->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => 'texto-invalido',
                'hora_fin' => '10:30',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('hora_inicio');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_create_appointment_with_non_existing_barber_returns_validation_error(): void
    {
        [$actor, $client, , $service] = $this->bootstrapScenario();

        $date = now()->addDays(2)->toDateString();

        $this->from(route('appointments.create'))
            ->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => 999999,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('barber_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_create_appointment_with_past_date_returns_validation_error(): void
    {
        [$actor, $client, $barber, $service] = $this->bootstrapScenario();

        $pastDate = now()->subDay()->toDateString();

        $this->from(route('appointments.create'))
            ->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $pastDate,
                'hora_inicio' => '10:00',
                'hora_fin' => '10:30',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('fecha');

        $this->assertDatabaseCount('appointments', 0);
    }

    // --- Contexto: solapamientos ---

    public function test_create_overlapping_appointment_for_same_barber_returns_error_on_start_time(): void
    {
        [$actor, $client, $barber, $service] = $this->bootstrapScenario();

        $date = now()->addDays(3)->toDateString();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $this->from(route('appointments.create'))
            ->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '10:15',
                'hora_fin' => '10:45',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('hora_inicio');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_create_overlapping_appointment_for_different_barber_is_allowed(): void
    {
        [$actor, $client, $barberA, $service] = $this->bootstrapScenario();

        $barberB = Barber::factory()->create(['activo' => true]);
        $date = now()->addDays(3)->toDateString();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barberA->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'confirmada',
        ]);

        $this->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barberB->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '11:00',
                'hora_fin' => '11:30',
            ])
            ->assertRedirect(route('appointments.index'));

        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_create_appointment_with_end_time_before_start_time_returns_validation_error(): void
    {
        [$actor, $client, $barber, $service] = $this->bootstrapScenario();

        $date = now()->addDays(2)->toDateString();

        $this->from(route('appointments.create'))
            ->actingAs($actor)
            ->post(route('appointments.store'), [
                'client_id' => $client->id,
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => $date,
                'hora_inicio' => '12:30',
                'hora_fin' => '12:00',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('hora_fin');

        $this->assertDatabaseCount('appointments', 0);
    }

    private function bootstrapScenario(): array
    {
        $actor = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'recepcionista',
            'guard_name' => 'web',
        ]);
        $actor->assignRole($role);

        $barber = Barber::factory()->create(['activo' => true]);
        $client = Client::factory()->create();
        $service = Service::factory()->create([
            'activo' => true,
            'duracion_min' => 30,
        ]);

        return [$actor, $client, $barber, $service];
    }
}
