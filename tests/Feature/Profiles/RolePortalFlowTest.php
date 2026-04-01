<?php

namespace Tests\Feature\Profiles;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePortalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_assigns_cliente_role_and_profile(): void
    {
        Role::query()->firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);

        User::factory()->create([
            'email' => 'bootstrap-admin@example.com',
            'email_verified_at' => now(),
        ]);

        $this->post('/register', [
            'name' => 'Nuevo Cliente',
            'email' => 'nuevo-cliente@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'nuevo-cliente@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertNotNull($user->clientProfile);
    }

    public function test_cliente_can_create_own_appointment(): void
    {
        [$clientUser, $barber, $service] = $this->bootstrapClientScenario();

        $this->actingAs($clientUser)
            ->post(route('client.appointments.store'), [
                'barber_id' => $barber->id,
                'service_id' => $service->id,
                'fecha' => now()->addDay()->toDateString(),
                'hora_inicio' => '11:00',
                'hora_fin' => '11:30',
            ])
            ->assertRedirect(route('client.appointments.index'));

        $this->assertDatabaseHas('appointments', [
            'client_id' => $clientUser->clientProfile->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_barbero_can_update_only_his_appointment_status(): void
    {
        [$barberUser, $otherBarber, $ownAppointment, $otherAppointment] = $this->bootstrapBarberScenario();

        $this->actingAs($barberUser)
            ->patch(route('barber.appointments.status', $ownAppointment), [
                'estado' => 'en_proceso',
            ])
            ->assertRedirect();

        $this->assertSame('en_proceso', $ownAppointment->fresh()->estado);

        $this->actingAs($barberUser)
            ->patch(route('barber.appointments.status', $otherAppointment), [
                'estado' => 'completada',
            ])
            ->assertForbidden();

        $this->assertNotSame('completada', $otherAppointment->fresh()->estado);
    }

    public function test_barbero_can_upload_profile_photo_with_ordered_path(): void
    {
        Role::query()->firstOrCreate(['name' => 'barbero', 'guard_name' => 'web']);
        $barberUser = User::factory()->create(['email_verified_at' => now()]);
        $barberUser->assignRole('barbero');
        $barber = Barber::query()->create(['user_id' => $barberUser->id, 'activo' => true]);

        Storage::fake('public');

        $this->actingAs($barberUser)
            ->put(route('barber.profile.update'), [
                'especialidades' => 'Fade',
                'descripcion' => 'Barbero senior',
                'foto' => UploadedFile::fake()->create('avatar.jpg', 120, 'image/jpeg'),
            ])
            ->assertRedirect();

        $path = (string) $barber->fresh()->foto;

        $this->assertNotSame('', $path);
        $this->assertStringContainsString("barbers/{$barberUser->id}/".now()->format('d/m/Y').'/', $path);
        Storage::disk('public')->assertExists($path);
    }

    private function bootstrapClientScenario(): array
    {
        Role::query()->firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => 'barbero', 'guard_name' => 'web']);

        $clientUser = User::factory()->create(['email_verified_at' => now()]);
        $clientUser->assignRole('cliente');

        Client::query()->create([
            'user_id' => $clientUser->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => true],
        ]);

        $barberUser = User::factory()->create();
        $barberUser->assignRole('barbero');

        $barber = Barber::query()->create([
            'user_id' => $barberUser->id,
            'activo' => true,
        ]);

        $service = Service::query()->create([
            'nombre' => 'Corte',
            'categoria' => 'corte',
            'precio' => 200,
            'duracion_min' => 30,
            'activo' => true,
        ]);

        return [$clientUser, $barber, $service];
    }

    private function bootstrapBarberScenario(): array
    {
        Role::query()->firstOrCreate(['name' => 'barbero', 'guard_name' => 'web']);

        $barberUser = User::factory()->create(['email_verified_at' => now()]);
        $barberUser->assignRole('barbero');
        $barber = Barber::query()->create(['user_id' => $barberUser->id, 'activo' => true]);

        $otherBarberUser = User::factory()->create();
        $otherBarberUser->assignRole('barbero');
        $otherBarber = Barber::query()->create(['user_id' => $otherBarberUser->id, 'activo' => true]);

        $clientUser = User::factory()->create();
        $client = Client::query()->create(['user_id' => $clientUser->id]);

        $service = Service::query()->create([
            'nombre' => 'Barba',
            'categoria' => 'barba',
            'precio' => 150,
            'duracion_min' => 20,
            'activo' => true,
        ]);

        $ownAppointment = Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fin' => '10:30',
            'estado' => 'pendiente',
        ]);

        $otherAppointment = Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $otherBarber->id,
            'service_id' => $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '11:00',
            'hora_fin' => '11:30',
            'estado' => 'pendiente',
        ]);

        return [$barberUser, $otherBarber, $ownAppointment, $otherAppointment];
    }
}
