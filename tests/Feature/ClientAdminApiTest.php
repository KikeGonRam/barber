<?php

namespace Tests\Feature;

use App\Models\Appointment;
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
 * Integración real contra el Mongo local de pruebas. GET/POST/PUT/DELETE
 * admin/clients (App\Http\Controllers\Api\Admin\Client\ClientAdminController)
 * -- hasta ahora solo tenía cobertura del 403 para roles no-admin
 * (EngineerRoleAuthorizationTest), sin ningún camino exitoso probado.
 * Client usa HasSlug (getRouteKeyName() = 'slug', guardrail #20): las
 * rutas con {client} resuelven por slug, no por id.
 */
class ClientAdminApiTest extends TestCase
{
    private string $adminToken = 'test-client-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Clientes', 'email' => 'admin-clients@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeClient(string $name, string $email, ?string $telefono = '5551234567'): Client
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password']);

        return Client::create(['user_id' => (string) $user->id, 'telefono' => $telefono, 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    public function test_get_clients_lists_with_pagination_shape(): void
    {
        $this->makeClient('Cliente Uno', 'cliente-uno-'.Str::uuid().'@test.local');
        $this->makeClient('Cliente Dos', 'cliente-dos-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/clients');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure(['data', 'total', 'current_page', 'last_page', 'per_page']);
    }

    public function test_get_clients_filters_by_search(): void
    {
        $this->makeClient('Roberto Salas', 'roberto-'.Str::uuid().'@test.local');
        $this->makeClient('Mariana Cruz', 'mariana-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/clients?search=Roberto');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Roberto Salas');
    }

    public function test_show_returns_profile_with_appointment_history(): void
    {
        $client = $this->makeClient('Cliente Detalle', 'detalle-'.Str::uuid().'@test.local');
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) Str::uuid(),
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->subDay()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 250,
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/clients/'.$client->slug);

        $response->assertOk();
        $response->assertJsonPath('data.totalAppointments', 1);
        // assertEquals, no assertJsonPath: json_encode no preserva ".0" en un
        // float sin parte fraccionaria, así que decodifica como int (250) y
        // 250 === 250.0 es false con la comparación estricta de assertJsonPath.
        $this->assertEquals(250.0, $response->json('data.totalSpent'));
        $response->assertJsonCount(1, 'data.appointments');
    }

    public function test_show_includes_loyalty_level_points_and_staff_notes(): void
    {
        // Lealtad y notas son lo que la ficha 360 necesita para que quien
        // atiende vea al cliente completo; el detalle no las devolvía.
        $client = $this->makeClient('Cliente Lealtad', 'lealtad-'.Str::uuid().'@test.local');
        $client->update(['nivel' => 'oro', 'puntos' => 120, 'notas' => 'Alérgico al after shave con alcohol.']);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/clients/'.$client->slug);

        $response->assertOk();
        $response->assertJsonPath('data.nivel', 'oro');
        $response->assertJsonPath('data.puntos', 120);
        $response->assertJsonPath('data.notas', 'Alérgico al after shave con alcohol.');
    }

    public function test_days_since_last_appointment_is_a_whole_number(): void
    {
        // diffInDays() devuelve flotante en esta versión de Carbon: sin el
        // cast, la ficha mostraba "hace 0.043811839895833336 días".
        $client = $this->makeClient('Cliente Dias', 'dias-'.Str::uuid().'@test.local');
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) Str::uuid(),
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 100,
        ]);

        $dias = $this->withToken($this->adminToken)
            ->getJson('/api/v1/admin/clients/'.$client->slug)
            ->assertOk()
            ->json('data.daysSinceLastAppointment');

        $this->assertIsInt($dias);
    }

    public function test_update_saves_and_clears_staff_notes(): void
    {
        $client = $this->makeClient('Cliente Notas', 'notas-'.Str::uuid().'@test.local');

        $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/clients/'.$client->slug, ['notas' => 'Prefiere fade bajo, sin máquina en la barba.'])
            ->assertOk();
        $this->assertSame('Prefiere fade bajo, sin máquina en la barba.', $client->fresh()->notas);

        // Mandar null es la forma de borrarlas: con isset() en el controlador
        // este caso dejaría la nota anterior viva para siempre.
        $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/clients/'.$client->slug, ['notas' => null])
            ->assertOk();
        $this->assertNull($client->fresh()->notas);
    }

    public function test_update_rejects_notes_longer_than_the_limit(): void
    {
        $client = $this->makeClient('Cliente Limite', 'limite-'.Str::uuid().'@test.local');

        $this->withToken($this->adminToken)
            ->putJson('/api/v1/admin/clients/'.$client->slug, ['notas' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notas']);
    }

    public function test_segmentation_returns_counts_with_percentages(): void
    {
        $this->makeClient('Cliente Segmento', 'segmento-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/clients/segmentation/data');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['new' => ['count', 'percentage']]]);
    }

    public function test_store_creates_user_and_client_profile(): void
    {
        $email = 'nuevo-cliente-'.Str::uuid().'@test.local';

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/admin/clients', [
            'name' => 'Cliente Nuevo',
            'email' => $email,
            'telefono' => '5559876543',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Cliente Nuevo');
        $response->assertJsonPath('data.totalAppointments', 0);

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertNotNull($user->clientProfile);
    }

    public function test_store_requires_valid_unique_email_and_password(): void
    {
        $existing = $this->makeClient('Ya Existe', 'ya-existe-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/admin/clients', [
            'name' => 'Duplicado',
            'email' => $existing->user->email,
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_update_persists_client_and_linked_user_fields(): void
    {
        $client = $this->makeClient('Nombre Original', 'original-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->putJson('/api/v1/admin/clients/'.$client->slug, [
            'name' => 'Nombre Actualizado',
            'telefono' => '5550001111',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Nombre Actualizado');
        $response->assertJsonPath('data.telefono', '5550001111');

        $fresh = $client->fresh();
        $this->assertSame('5550001111', $fresh->telefono);
        $this->assertSame('Nombre Actualizado', $fresh->user->name);
    }

    public function test_destroy_deletes_a_client_with_no_appointments(): void
    {
        $client = $this->makeClient('Cliente Borrable', 'borrable-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->deleteJson('/api/v1/admin/clients/'.$client->slug);

        $response->assertOk();
        $this->assertNull(Client::find($client->id));
    }

    public function test_destroy_is_blocked_when_client_has_appointments(): void
    {
        $client = $this->makeClient('Cliente Con Citas', 'con-citas-'.Str::uuid().'@test.local');
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) Str::uuid(),
            'service_id' => (string) Str::uuid(),
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $response = $this->withToken($this->adminToken)->deleteJson('/api/v1/admin/clients/'.$client->slug);

        $response->assertStatus(422);
        $this->assertNotNull(Client::find($client->id));
    }

    public function test_export_streams_a_csv_with_the_client_list(): void
    {
        $this->makeClient('Cliente CSV', 'csv-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->get('/api/v1/admin/clients/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('Cliente CSV', $response->streamedContent());
    }

    public function test_non_admin_cannot_reach_client_admin_endpoints(): void
    {
        $client = $this->makeClient('Cliente Regular', 'regular-'.Str::uuid().'@test.local');
        $token = 'test-non-admin-client-token';
        MobileApiToken::create(['user_id' => (string) $client->user_id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/admin/clients')->assertForbidden();
        $this->withToken($token)->postJson('/api/v1/admin/clients', [])->assertForbidden();
    }
}
