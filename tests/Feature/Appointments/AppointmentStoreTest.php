<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        ]);
    }

    public function test_recepcionista_can_create_appointment_when_schedule_is_available(): void
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
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'fecha' => $date,
            'hora_inicio' => '10:00',
            'hora_fin' => '10:30',
        ]);
    }

    public function test_store_rejects_overlapping_appointment_for_same_barber(): void
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
                'estado' => 'pendiente',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('hora_inicio');

        $this->assertSame(1, Appointment::query()->count());
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

        $barberUser = User::factory()->create();
        $clientUser = User::factory()->create();

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $client = Client::query()->create([
            'user_id' => $clientUser->id,
        ]);

        $service = Service::query()->create([
            'nombre' => 'Corte clásico',
            'categoria' => 'corte',
            'precio' => 200,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        return [$actor, $client, $barber, $service];
    }
}
