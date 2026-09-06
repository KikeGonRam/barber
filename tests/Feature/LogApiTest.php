<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API de auditoría/logs (Fase 9.9): amplía LogController a paridad con
 * Log\ActivityLogController (web) — filtros de evento/fecha/causante y
 * bloque de estadísticas que antes solo existían en el panel Blade.
 */
class LogApiTest extends TestCase
{
    private string $adminToken = 'test-log-admin-token';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Logs API', 'email' => 'admin-logs-api@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $this->admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);

        // activity_log es global y otras suites (LogsActivity en varios modelos)
        // dejan entradas reales sin limpiar entre clases de test — se limpia
        // aquí para que las aserciones de stats globales sean deterministas.
        Activity::query()->delete();
    }

    protected function tearDown(): void
    {
        Activity::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_filters_by_event_causer_and_date_range_with_stats(): void
    {
        Activity::create([
            'log_name' => 'appointment', 'event' => 'created', 'description' => 'Cita creada',
            'causer_type' => User::class, 'causer_id' => (string) $this->admin->id, 'created_at' => now(),
        ]);
        Activity::create([
            'log_name' => 'appointment', 'event' => 'updated', 'description' => 'Cita actualizada',
            'causer_type' => User::class, 'causer_id' => (string) $this->admin->id, 'created_at' => now()->subDays(5),
        ]);
        Activity::create([
            'log_name' => 'payment', 'event' => 'created', 'description' => 'Pago registrado',
            'causer_type' => User::class, 'causer_id' => (string) $this->admin->id, 'created_at' => now(),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/logs?event=created&log_name=appointment');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Cita creada')
            ->assertJsonPath('stats.total', 3)
            ->assertJsonPath('stats.creates', 2)
            ->assertJsonPath('stats.updates', 1)
            ->assertJsonStructure(['events', 'log_names']);

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/logs?causer='.urlencode($this->admin->name))
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/logs?fecha_desde='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $recepRole = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $recepUser = User::create(['name' => 'Recep Logs', 'email' => 'recep-logs@test.local', 'password' => 'password']);
        $recepUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepRole->id]])->save();
        $token = 'test-recep-log-token';
        MobileApiToken::create(['user_id' => (string) $recepUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/logs')->assertStatus(403);
    }
}
