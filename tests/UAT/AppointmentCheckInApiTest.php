<?php

namespace Tests\UAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Check-in («Llegó»): POST /api/v1/appointments/{code}/check-in registra la hora de llegada de una cita de hoy sin
 * cambiar su estado. Lo usan la recepción, el administrador y el barbero de la cita.
 */
class AppointmentCheckInApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Service::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(string $roleName, string $plaintext): array
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Check-in', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return [$user, $plaintext];
    }

    private function appointment(string $estado = 'confirmada', ?string $fecha = null, ?Barber $barber = null): Appointment
    {
        $clientUser = User::create(['name' => 'Cliente Check-in', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550001234', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barber ??= Barber::create(['user_id' => (string) User::create(['name' => 'Barbero Check-in', 'email' => Str::uuid().'@test.local', 'password' => 'password'])->id, 'nombre' => 'Barbero Check-in', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte Check-in', 'categoria' => 'corte', 'precio' => 150, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => $fecha ?? now()->toDateString(), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => $estado,
        ]);
    }

    private function checkIn(string $token, Appointment $appointment): TestResponse
    {
        return $this->withToken($token)->postJson("/api/v1/appointments/{$appointment->getAttribute('code')}/check-in");
    }

    public function test_reception_marks_arrival_without_changing_the_status(): void
    {
        [, $token] = $this->tokenFor('recepcionista', 'tok-recepcion-checkin');
        $appointment = $this->appointment('confirmada');

        $this->checkIn($token, $appointment)
            ->assertOk()
            ->assertJsonPath('message', 'Llegada registrada.')
            ->assertJsonStructure(['llegada_en']);

        $fresh = $appointment->fresh();
        $this->assertNotNull($fresh->getAttribute('llegada_en'));
        $this->assertSame('confirmada', $fresh->estado);
    }

    public function test_check_in_is_idempotent(): void
    {
        [, $token] = $this->tokenFor('recepcionista', 'tok-recepcion-idem');
        $appointment = $this->appointment('pendiente');

        $primera = $this->checkIn($token, $appointment)->assertOk()->json('llegada_en');
        $segunda = $this->checkIn($token, $appointment)->assertOk()->json('llegada_en');

        $this->assertSame($primera, $segunda);
    }

    public function test_only_todays_open_appointments_accept_check_in(): void
    {
        [, $token] = $this->tokenFor('recepcionista', 'tok-recepcion-reglas');

        $manana = $this->appointment('confirmada', now()->addDay()->toDateString());
        $this->checkIn($token, $manana)->assertStatus(422);

        $terminada = $this->appointment('completada');
        $this->checkIn($token, $terminada)->assertStatus(422);

        $this->assertNull($manana->fresh()->getAttribute('llegada_en'));
        $this->assertNull($terminada->fresh()->getAttribute('llegada_en'));
    }

    public function test_barber_can_only_check_in_his_own_appointments_and_clients_cannot(): void
    {
        [$barberUser, $barberToken] = $this->tokenFor('barbero', 'tok-barbero-checkin');
        $propio = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero propio', 'activo' => true]);
        $miCita = $this->appointment('confirmada', null, $propio);
        $ajena = $this->appointment('confirmada');

        $this->checkIn($barberToken, $miCita)->assertOk();
        $this->checkIn($barberToken, $ajena)->assertStatus(403);

        [, $clientToken] = $this->tokenFor('cliente', 'tok-cliente-checkin');
        $this->checkIn($clientToken, $ajena)->assertStatus(403);
    }
}
