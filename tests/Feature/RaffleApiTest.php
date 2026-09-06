<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\RaffleResult;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API de historial de sorteos (Fase 9.9), puerto de Loyalty\RaffleController
 * (web): antes esta pantalla no existía en la API.
 */
class RaffleApiTest extends TestCase
{
    private string $adminToken = 'test-raffle-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Sorteos API', 'email' => 'admin-sorteos-api@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        RaffleResult::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_sees_raffle_history_with_stats_and_redemption_status(): void
    {
        $winnerUser = User::create(['name' => 'Ganador API', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $winner = Client::create(['user_id' => (string) $winnerUser->id, 'telefono' => '5551234567', 'nivel' => 'vip']);

        RaffleResult::create([
            'client_id' => (string) $winner->id, 'mes' => '2026-01', 'premio' => 'Corte gratis',
            'nivel_ganador' => 'vip', 'vence_en' => now()->addDays(30),
        ]);
        RaffleResult::create([
            'client_id' => (string) $winner->id, 'mes' => '2025-12', 'premio' => 'Descuento 50%',
            'nivel_ganador' => 'vip', 'vence_en' => now()->subDays(5), 'reclamado_en' => now()->subDays(10),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/raffles');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.reclamados', 1)
            ->assertJsonPath('stats.vigentes', 1)
            ->assertJsonPath('data.0.client.user.name', 'Ganador API');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $recepRole = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $recepUser = User::create(['name' => 'Recep Sorteos', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $recepUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepRole->id]])->save();
        $token = 'test-recep-raffle-token';
        MobileApiToken::create(['user_id' => (string) $recepUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/raffles')->assertStatus(403);
    }
}
