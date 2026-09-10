<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
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
 * App\Http\Controllers\Api\Admin\Barber\BarberAdminController -- cubre los
 * métodos que BarberAdminRatingTest no toca (getBarbers/getSchedule/
 * getRegularClients/update); show()/getPerformanceStats() ya tienen
 * cobertura propia ahí. Barber usa HasSlug (guardrail #20): las rutas con
 * {barber} resuelven por slug, no por id.
 */
class BarberAdminApiTest extends TestCase
{
    private string $adminToken = 'test-barber-admin-list-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Barberos', 'email' => 'admin-barbers-list@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
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

    private function makeBarber(string $name, string $email, bool $activo = true): Barber
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password']);

        return Barber::create(['user_id' => (string) $user->id, 'nombre' => $name, 'activo' => $activo]);
    }

    public function test_get_barbers_lists_only_active_barbers_with_today_metrics(): void
    {
        $activo = $this->makeBarber('Barbero Activo', 'barbero-activo-list@test.local');
        $this->makeBarber('Barbero Inactivo', 'barbero-inactivo-list@test.local', false);

        Appointment::create([
            'client_id' => (string) Str::uuid(),
            'barber_id' => (string) $activo->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 300,
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/barbers');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Barbero Activo');
        $response->assertJsonPath('data.0.appointmentsToday', 1);
        $this->assertEquals(300.0, $response->json('data.0.revenueToday'));
    }

    public function test_get_schedule_returns_appointments_for_the_requested_date(): void
    {
        $barber = $this->makeBarber('Barbero Agenda', 'barbero-agenda-list@test.local');
        $client = Client::create(['user_id' => (string) User::create(['name' => 'Cliente Agenda', 'email' => 'cliente-agenda-list@test.local', 'password' => 'password'])->id, 'telefono' => '5551112222', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $date = now()->addDay()->format('Y-m-d');

        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => $date,
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'pendiente',
        ]);
        // Otra fecha: no debe aparecer en la agenda del día solicitado.
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '12:00:00',
            'hora_fin' => '12:30:00',
            'estado' => 'pendiente',
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/barbers/'.$barber->slug.'/schedule?date='.$date);

        $response->assertOk();
        $response->assertJsonPath('date', $date);
        $response->assertJsonCount(1, 'appointments');
        $response->assertJsonPath('appointments.0.clientName', 'Cliente Agenda');
    }

    public function test_get_regular_clients_returns_appointment_counts_and_spend(): void
    {
        $barber = $this->makeBarber('Barbero Clientes', 'barbero-clientes-list@test.local');
        $client = Client::create(['user_id' => (string) User::create(['name' => 'Cliente Recurrente', 'email' => 'cliente-recurrente-list@test.local', 'password' => 'password'])->id, 'telefono' => '5553334444', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->subDay()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 150,
        ]);
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 150,
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/barbers/'.$barber->slug.'/clients');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Cliente Recurrente');
        $response->assertJsonPath('data.0.appointmentCount', 2);
        $this->assertEquals(300.0, $response->json('data.0.totalSpent'));
    }

    public function test_update_persists_barber_profile_and_linked_user_fields(): void
    {
        $barber = $this->makeBarber('Nombre Viejo', 'barbero-update-list@test.local');

        $response = $this->withToken($this->adminToken)->putJson('/api/v1/admin/barbers/'.$barber->slug, [
            'especialidades' => 'Fade, Barba',
            'name' => 'Nombre Nuevo',
        ]);

        $response->assertOk();
        $fresh = $barber->fresh();
        $this->assertSame('Fade, Barba', $fresh->especialidades);
        $this->assertSame('Nombre Nuevo', $fresh->user->name);
    }

    public function test_non_admin_cannot_reach_barber_admin_endpoints(): void
    {
        $barber = $this->makeBarber('Barbero Bloqueado', 'barbero-bloqueado-list@test.local');
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $barber->user->forceFill(['role_id' => [(string) $role->id]])->save();
        $token = 'test-non-admin-barber-list-token';
        MobileApiToken::create(['user_id' => (string) $barber->user_id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/admin/barbers')->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/admin/barbers/'.$barber->slug, [])->assertForbidden();
    }
}
