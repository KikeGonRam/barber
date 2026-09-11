<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas.
 * PATCH /api/v1/appointments/{cita}/status — el endpoint que aplica la
 * máquina de estados sin exigir el payload completo de la cita.
 *
 * Regresión que motivó este archivo: el guard exigía hasRole('barbero') y
 * contradecía a AppointmentStatusService::ROLE_MAP, que ya declaraba a
 * administrador y recepcionista autorizados en todas las transiciones
 * (roleCanSet() estaba definido y sin usarse en todo el proyecto). Como
 * PUT /appointments/{cita} exige 'fecha' => after_or_equal:today, recepción
 * no podía marcar "no asistió" ni "completada" sobre una cita del día
 * anterior por ningún camino: el PATCH le daba 403 y el PUT un 422 de
 * validación. Verificado contra la API real antes de tocar el código.
 */
class AppointmentStatusApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        // forceDelete(): un delete() masivo deja el registro soft-deleted con
        // bloquea_horario en true y choca con appointments_active_slot_unique.
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(string $roleName, string $email): string
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $plain = 'token-'.$roleName.'-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return $plain;
    }

    private function barberWithToken(string $email): array
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Barbero Estado', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $barber = Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero Estado', 'activo' => true]);

        $plain = 'token-barbero-'.uniqid();
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain)]);

        return [$barber, $plain];
    }

    private function appointment(string $estado, ?string $barberId = null, ?string $fecha = null): Appointment
    {
        return Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => $barberId ?? (string) Str::uuid(),
            'service_id' => (string) Str::uuid(),
            'fecha' => $fecha ?? now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => $estado,
        ]);
    }

    public function test_recepcionista_can_confirm_a_pending_appointment(): void
    {
        $token = $this->tokenFor('recepcionista', 'recepcion-estado@test.local');
        $appointment = $this->appointment('pendiente');

        $response = $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', ['estado' => 'confirmada']);

        $response->assertOk();
        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_admin_can_mark_no_show_on_a_past_appointment(): void
    {
        // El caso que no tenía ningún camino: PATCH daba 403 por rol y PUT
        // un 422 porque exige fecha >= hoy. Un no-show se marca después.
        $token = $this->tokenFor('administrador', 'admin-estado@test.local');
        $appointment = $this->appointment('confirmada', null, now()->subDays(2)->format('Y-m-d'));

        $response = $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', ['estado' => 'no_asistio']);

        $response->assertOk();
        $this->assertSame('no_asistio', Appointment::find($appointment->id)->estado);
    }

    public function test_assigned_barber_can_still_change_the_status_of_their_own_appointment(): void
    {
        [$barber, $token] = $this->barberWithToken('barbero-propio@test.local');
        $appointment = $this->appointment('confirmada', (string) $barber->id);

        $response = $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', ['estado' => 'en_proceso']);

        $response->assertOk();
        $this->assertSame('en_proceso', Appointment::find($appointment->id)->estado);
    }

    public function test_a_barber_cannot_touch_an_appointment_assigned_to_someone_else(): void
    {
        [, $token] = $this->barberWithToken('barbero-ajeno@test.local');
        $appointment = $this->appointment('confirmada', (string) Str::uuid());

        $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', ['estado' => 'completada'])
            ->assertForbidden();

        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_a_client_cannot_use_this_endpoint_and_keeps_using_delete_to_cancel(): void
    {
        $token = $this->tokenFor('cliente', 'cliente-estado@test.local');
        $appointment = $this->appointment('confirmada');

        $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', ['estado' => 'cancelada'])
            ->assertForbidden();

        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_an_invalid_transition_is_rejected_with_the_real_reason(): void
    {
        $token = $this->tokenFor('administrador', 'admin-transicion@test.local');
        $appointment = $this->appointment('completada');

        $response = $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', ['estado' => 'pendiente']);

        $response->assertStatus(422);
        $response->assertJsonPath('message', "No se puede pasar la cita de 'completada' a 'pendiente'.");
        $this->assertSame('completada', Appointment::find($appointment->id)->estado);
    }

    public function test_notes_can_be_attached_while_changing_the_status(): void
    {
        $token = $this->tokenFor('recepcionista', 'recepcion-notas@test.local');
        $appointment = $this->appointment('confirmada');

        $this->withToken($token)
            ->patchJson('/api/v1/appointments/'.$appointment->code.'/status', [
                'estado' => 'completada',
                'notas' => 'Cliente pidió el mismo corte del mes pasado.',
            ])
            ->assertOk();

        $fresh = Appointment::find($appointment->id);
        $this->assertSame('completada', $fresh->estado);
        $this->assertSame('Cliente pidió el mismo corte del mes pasado.', $fresh->notas);
    }
}
