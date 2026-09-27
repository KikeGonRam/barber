<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * T054 (HT-19): entradas maliciosas. Operadores de Mongo metidos en JSON o en la URL
 * ({"$ne": null}, ?estado[$ne]=x) no deben llegar a una consulta, y los datos inválidos o
 * demasiado largos se rechazan con 422 (nunca un 500 ni un inicio de sesión regalado).
 */
class MaliciousInputTest extends TestCase
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
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenFor(string $role): string
    {
        $roleModel = Role::where('name', $role)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Seguridad', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $roleModel->id]])->save();
        $plain = 'token-'.Str::random(40);
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);

        return $plain;
    }

    public function test_login_with_mongo_operators_does_not_authenticate(): void
    {
        User::create(['name' => 'Víctima', 'email' => 'victima@test.local', 'password' => 'secreta-123']);

        foreach ([
            ['email' => ['$ne' => null], 'password' => ['$ne' => null]],
            ['email' => 'victima@test.local', 'password' => ['$gt' => '']],
            ['email' => ['$regex' => '.*'], 'password' => 'x'],
            // Arreglo sin operador: tampoco debe tronar (500) antes de validar.
            ['email' => ['victima@test.local'], 'password' => ['secreta-123']],
        ] as $payload) {
            $response = $this->postJson('/api/v1/auth/login', $payload);
            $this->assertSame(422, $response->getStatusCode(), 'Login con operadores: '.json_encode($payload));
            $this->assertNull($response->json('token'));
        }
    }

    public function test_operators_in_query_filters_are_rejected(): void
    {
        $token = $this->tokenFor('administrador');
        foreach (['completada', 'cancelada'] as $estado) {
            Appointment::create(['client_id' => (string) Str::uuid(), 'barber_id' => (string) Str::uuid(), 'service_id' => (string) Str::uuid(), 'fecha' => now()->addDay()->format('Y-m-d'), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => $estado]);
        }

        // Sin la protección, estado[$ne]=nada devolvía todas las citas.
        foreach (['/api/v1/appointments?estado[$ne]=nada', '/api/v1/appointments?barber_id[$exists]=1', '/api/v1/waitlist?estado[$ne]=x', '/api/v1/clients?q[$regex]=.*'] as $uri) {
            $this->withToken($token)->getJson($uri)->assertStatus(422);
        }
    }

    public function test_operators_in_public_endpoints_are_rejected(): void
    {
        $this->getJson('/api/v1/availability/slots?barber_id[$ne]=1&service_id[$ne]=1&date=2026-10-01')->assertStatus(422);
        $this->getJson('/api/v1/products?q[$ne]=x')->assertStatus(422);
        $this->postJson('/api/v1/chatbot/query', ['message' => ['$gt' => '']])->assertStatus(422);
        $this->postJson('/api/v1/auth/forgot-password', ['email' => ['$ne' => null]])->assertStatus(422);
    }

    public function test_nested_operator_keys_inside_a_json_body_are_rejected(): void
    {
        $token = $this->tokenFor('cliente');

        $this->withToken($token)->putJson('/api/v1/profile', ['name' => 'Ok', 'preferences' => ['theme' => ['$where' => 'sleep(1000)']]])
            ->assertStatus(422);
    }

    public function test_invalid_and_oversized_data_is_rejected_without_server_errors(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => str_repeat('A', 5000), 'email' => 'no-es-correo', 'password' => '1'])
            ->assertStatus(422);
        $this->postJson('/api/v1/chatbot/query', ['message' => str_repeat('x', 2500)])->assertStatus(422);
        $this->getJson('/api/v1/availability/slots?barber_id=x&service_id=y&date=no-es-fecha')->assertStatus(422);
    }

    public function test_script_tags_are_stored_as_plain_text_not_executed(): void
    {
        $token = $this->tokenFor('cliente');
        $payload = '<script>alert(1)</script>';

        $this->withToken($token)->putJson('/api/v1/profile', ['name' => $payload])->assertSuccessful();

        // La API devuelve JSON (el texto tal cual, escapado por el navegador al pintarlo), nunca HTML.
        $me = $this->withToken($token)->getJson('/api/v1/auth/me');
        $this->assertStringContainsString('application/json', (string) $me->headers->get('Content-Type'));
    }
}
