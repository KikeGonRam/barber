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
 * Integración real contra el Mongo local de pruebas. Cubre
 * GET /api/v1/appointments/calendar-data, agregado para el calendario del
 * frontend Nuxt (ver frontend-urban/.claude/skills/nuxt-migration-plan/
 * SKILL.md, Fase 7) — puerto del equivalente web
 * (Appointment\AppointmentController::calendarData()), que solo es
 * alcanzable con sesión + permiso 'citas.gestionar'.
 */
class AppointmentCalendarApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(User $user, string $plaintext): string
    {
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    public function test_recepcionista_gets_calendar_events_as_fullcalendar_shape(): void
    {
        $role = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Recepcion Calendar', 'email' => 'recepcion-calendar@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $barberUser = User::create(['name' => 'Barbero Calendar', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Calendar', 'activo' => true]);
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-calendar');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments/calendar-data?start='.now()->subDay()->toDateString().'&end='.now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonStructure([
            '*' => ['id', 'title', 'start', 'end', 'color', 'textColor', 'extendedProps' => ['cliente', 'servicio', 'barbero', 'estado']],
        ]);
    }

    public function test_cliente_cannot_access_calendar_data(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Calendar', 'email' => 'cliente-calendar@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $token = $this->tokenFor($user, 'test-plaintext-token-calendar-client');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments/calendar-data');

        $response->assertForbidden();
    }
}
