<?php

namespace Tests\Feature;

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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre los filtros
 * opcionales agregados a GET /api/v1/appointments (estado/barber_id/fecha)
 * para la lista de citas del frontend Nuxt — ver
 * frontend-urban/.claude/skills/nuxt-migration-plan/SKILL.md, Fase 9.2.
 * Aditivo: sin estos query params el endpoint se comporta igual que antes
 * (cubierto por el caso "sin filtros" abajo).
 */
class AppointmentApiTest extends TestCase
{
    private User $adminUser;

    private Barber $barberA;

    private Barber $barberB;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->adminUser = User::create(['name' => 'Admin Appts', 'email' => 'admin-appts@test.local', 'password' => 'password']);
        $this->adminUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $barberUserA = User::create(['name' => 'Barbero A', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barberA = Barber::create(['user_id' => (string) $barberUserA->id, 'nombre' => 'Barbero A', 'activo' => true]);
        $barberUserB = User::create(['name' => 'Barbero B', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barberB = Barber::create(['user_id' => (string) $barberUserB->id, 'nombre' => 'Barbero B', 'activo' => true]);

        $clientUser = User::create(['name' => 'Cliente Appts', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550001111', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barberA->id, 'service_id' => (string) Str::uuid(),
            'fecha' => '2026-06-01', 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'pendiente',
        ]);
        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barberB->id, 'service_id' => (string) Str::uuid(),
            'fecha' => '2026-06-02', 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'confirmada',
        ]);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(User $user, string $plaintext): string
    {
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plaintext)]);

        return $plaintext;
    }

    public function test_admin_cannot_move_a_completed_appointment_back_to_pending_via_full_update(): void
    {
        // PUT /appointments/{id} (edición completa) no debe poder saltarse la
        // máquina de estados de AppointmentStatusService solo porque no es el
        // PATCH .../status del barbero -- ver Fase 3 (citas) del roadmap.
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-transition-guard');

        $client = Client::create(['telefono' => '5559998888', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barberA->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'), 'hora_inicio' => '14:00:00', 'hora_fin' => '14:30:00', 'estado' => 'completada',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/v1/appointments/{$appointment->code}", [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $this->barberA->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '14:00',
            'estado' => 'pendiente',
        ]);

        $response->assertStatus(422);
        $this->assertSame('completada', Appointment::find($appointment->id)->estado);
    }

    public function test_admin_can_edit_an_appointment_without_changing_its_state(): void
    {
        // Regresión: el guard de transición no debe bloquear una edición
        // legítima que no toca `estado` (o lo deja igual).
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-edit-no-transition');

        $client = Client::create(['telefono' => '5559997777', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barberA->id, 'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'), 'hora_inicio' => '14:00:00', 'hora_fin' => '14:30:00', 'estado' => 'pendiente',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/v1/appointments/{$appointment->code}", [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $this->barberA->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDay()->format('Y-m-d'),
            'hora_inicio' => '15:00',
            'estado' => 'confirmada',
            'notas' => 'Cambio de horario legítimo',
        ]);

        $response->assertOk();
        $fresh = Appointment::find($appointment->id);
        $this->assertSame('confirmada', $fresh->estado);
        $this->assertSame('Cambio de horario legítimo', $fresh->notas);
    }

    public function test_admin_lists_all_appointments_without_filters(): void
    {
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-all');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/appointments');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_filters_by_estado(): void
    {
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-estado');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/appointments?estado=confirmada');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.estado', 'confirmada');
    }

    public function test_admin_filters_by_barber_id(): void
    {
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-barber');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments?barber_id='.$this->barberA->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.barber.id', $this->barberA->id);
    }

    public function test_admin_filters_by_fecha(): void
    {
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-fecha');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments?fecha=2026-06-02');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.fecha', '2026-06-02');
    }

    public function test_booking_a_client_with_no_show_history_flags_the_appointment_as_requiring_deposit(): void
    {
        // Política anti-no-show (DepositService): 2+ inasistencias en 90 días
        // exige depósito en la siguiente reserva, sin importar quién reserva.
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-deposit');

        $client = Client::create(['telefono' => '5551112222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barberA->id, 'service_id' => (string) $service->id,
            'fecha' => now()->subDays(10)->format('Y-m-d'), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'no_asistio',
        ]);
        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barberA->id, 'service_id' => (string) $service->id,
            'fecha' => now()->subDays(20)->format('Y-m-d'), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'no_asistio',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/appointments', [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $this->barberA->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '15:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.deposito_requerido', true);
        $response->assertJsonPath('data.deposito_monto', 100); // 50% de 200 (default)
    }

    public function test_booking_a_client_without_no_show_history_does_not_require_deposit(): void
    {
        $token = $this->tokenFor($this->adminUser, 'test-token-appts-no-deposit');

        $client = Client::create(['telefono' => '5553334444', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/appointments', [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $this->barberA->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '16:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.deposito_requerido', false);
    }
}
