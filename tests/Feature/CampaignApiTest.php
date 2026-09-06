<?php

namespace Tests\Feature;

use App\Models\Campaign;
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
 * API de campañas de marketing (Fase 9.9), puerto de Campaign\CampaignController
 * (web): antes esta pantalla no existía en la API, solo en el panel Blade.
 */
class CampaignApiTest extends TestCase
{
    private User $admin;

    private string $adminToken = 'test-campaign-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create(['name' => 'Admin Campañas', 'email' => 'admin-campanas@test.local', 'password' => 'password']);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $this->admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        Campaign::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_admin_sees_segment_counts_and_recent_campaigns(): void
    {
        $clientUser = User::create(['name' => 'Cliente VIP', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550001111', 'nivel' => 'vip']);

        Campaign::create([
            'titulo' => 'Promo de prueba', 'cuerpo' => 'Cuerpo', 'segmento' => 'todos',
            'destinatarios' => 1, 'estado' => 'enviada', 'enviada_en' => now(),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/campaigns');

        $response->assertOk()
            ->assertJsonPath('segment_counts.vip', 1)
            ->assertJsonPath('segment_counts.todos', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.titulo', 'Promo de prueba')
            ->assertJsonStructure(['levels']);
    }

    public function test_admin_can_send_a_campaign_immediately(): void
    {
        $clientUser = User::create(['name' => 'Cliente Nuevo', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550002222', 'nivel' => 'nuevo']);

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/campaigns', [
            'titulo' => 'Bienvenida',
            'cuerpo' => 'Gracias por unirte',
            'segmento' => 'nuevo',
            'modo' => 'ahora',
        ]);

        $response->assertCreated();
        $this->assertSame(1, Campaign::where('titulo', 'Bienvenida')->firstOrFail()->destinatarios);
        $this->assertSame('enviada', Campaign::where('titulo', 'Bienvenida')->firstOrFail()->estado);
    }

    public function test_admin_can_schedule_a_campaign(): void
    {
        $clientUser = User::create(['name' => 'Cliente VIP 2', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        Client::create(['user_id' => (string) $clientUser->id, 'telefono' => '5550003333', 'nivel' => 'vip']);

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/campaigns', [
            'titulo' => 'Programada', 'cuerpo' => 'Cuerpo', 'segmento' => 'vip',
            'modo' => 'programar', 'programada_para' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertCreated();
        $campaign = Campaign::where('titulo', 'Programada')->firstOrFail();
        $this->assertSame('programada', $campaign->estado);
        $this->assertNull($campaign->enviada_en);
    }

    public function test_rejects_a_segment_with_no_clients(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/v1/campaigns', [
            'titulo' => 'Sin audiencia', 'cuerpo' => 'Cuerpo', 'segmento' => 'vip', 'modo' => 'ahora',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Campaign::where('titulo', 'Sin audiencia')->count());
    }

    public function test_non_admin_is_forbidden(): void
    {
        $recepRole = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $recepUser = User::create(['name' => 'Recep', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $recepUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $recepRole->id]])->save();
        $token = 'test-recep-campaign-token';
        MobileApiToken::create(['user_id' => (string) $recepUser->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/campaigns')->assertStatus(403);
    }
}
