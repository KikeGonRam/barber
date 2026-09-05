<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\RaffleResult;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre
 * GET /api/v1/appointments/chargeable, agregado para el selector de "Nuevo
 * Cobro" del frontend Nuxt (ver frontend-urban/.claude/skills/
 * nuxt-migration-plan/SKILL.md, Fase 9.3) — puerto de la consulta que usa
 * Payment\AppointmentController::create() (web) más los datos de lealtad/
 * premio de rifa que esa vista precalcula para el preview de descuento.
 */
class AppointmentChargeableApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        RaffleResult::query()->delete();
        Appointment::query()->delete();
        Service::query()->delete();
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

    public function test_recepcionista_gets_chargeable_appointments_with_loyalty_preview(): void
    {
        $role = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Recepcion Cobro', 'email' => 'recepcion-cobro@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $barberUser = User::create(['name' => 'Barbero Cobro', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Cobro', 'activo' => true]);

        $clientUser = User::create(['name' => 'Cliente VIP Cobro', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client = Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5551234567', 'nivel' => 'vip', 'puntos' => 40, 'total_citas' => 10]);

        $service = Service::create(['nombre' => 'Corte Cobro Test', 'categoria' => 'corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $chargeable = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        // No debe aparecer: ya cancelada.
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'cancelada',
        ]);

        RaffleResult::create([
            'client_id' => (string) $client->id,
            'mes' => now()->format('Y-m'),
            'premio' => 'Corte gratis',
            'nivel_ganador' => 'vip',
            'vence_en' => now()->addDays(5),
        ]);

        $token = $this->tokenFor($user, 'test-plaintext-token-chargeable');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments/chargeable');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonStructure([
            'data' => ['*' => [
                'id', 'code', 'fecha', 'hora_inicio', 'client_name', 'barber_name', 'service_name',
                'precio', 'nivel', 'nivel_label', 'nivel_pct', 'puntos_disponibles', 'premio_rifa',
            ]],
        ]);
        $response->assertJsonPath('data.0.id', (string) $chargeable->id);
        $response->assertJsonPath('data.0.precio', 200);
        $response->assertJsonPath('data.0.nivel', 'vip');
        $response->assertJsonPath('data.0.nivel_pct', 10);
        $response->assertJsonPath('data.0.puntos_disponibles', 40);
        $response->assertJsonPath('data.0.premio_rifa', 'Corte gratis');
    }

    public function test_cliente_cannot_access_chargeable_appointments(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Cobro', 'email' => 'cliente-cobro@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $token = $this->tokenFor($user, 'test-plaintext-token-chargeable-client');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/appointments/chargeable');

        $response->assertForbidden();
    }
}
