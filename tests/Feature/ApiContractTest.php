<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ata los ejemplos de `docs/contrato/*.json` (los mismos que Scribe publica en
 * el OpenAPI vía `@responseFile`) a las respuestas REALES de la API.
 *
 * Por qué existe: el OpenAPI tenía las rutas al día pero las respuestas
 * inventadas (ids `integer` donde Mongo da strings, `user.role` donde el API
 * devuelve `user.roles`...). Un tipo generado de eso sería falso con aspecto
 * oficial. Ver `.claude/skills/api-contract-plan/SKILL.md`.
 *
 * Qué compara: estructura, no valores. En cada nivel de objeto, el mismo
 * conjunto de claves y el mismo tipo JSON; `null` en cualquiera de los dos
 * lados es comodín porque no dice nada del tipo. Una clave nueva sin
 * documentar, o una documentada que desapareció, rompe la prueba.
 */
class ApiContractTest extends TestCase
{
    private User $adminUser;

    private User $clientUser;

    private Client $clientProfile;

    private Barber $barber;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->adminUser = User::create(['name' => 'Admin Contrato', 'email' => 'admin-contrato@test.local', 'password' => 'password']);
        $this->adminUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $adminRole->id]])->save();

        $clientRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $this->clientUser = User::create(['name' => 'Cliente Contrato', 'email' => 'cliente-contrato@test.local', 'password' => 'password123']);
        $this->clientUser->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $clientRole->id]])->save();
        $this->clientProfile = Client::create([
            'user_id' => (string) $this->clientUser->id, 'telefono' => '5512345678',
            'fecha_nacimiento' => '1995-04-12', 'sexo' => 'masculino', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0,
        ]);

        $barberUser = User::create(['name' => 'Barbero Contrato', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $this->barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Contrato', 'activo' => true]);
        $this->service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
    }

    protected function tearDown(): void
    {
        Order::query()->delete();
        Product::withTrashed()->forceDelete();
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

    private function appointmentFor(Client $client, string $fecha, string $estado = 'confirmada'): Appointment
    {
        return Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $this->barber->id, 'service_id' => (string) $this->service->id,
            'fecha' => $fecha, 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => $estado,
            'notas' => 'Traer foto de referencia.', 'precio_cobrado' => 250, 'propina_sugerida' => 25,
        ]);
    }

    public function test_login_matches_the_documented_contract(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cliente-contrato@test.local', 'password' => 'password123',
        ]);

        $response->assertOk();
        $this->assertMatchesContract('auth-login.200.json', $response->json());
    }

    public function test_me_matches_the_documented_contract(): void
    {
        $token = $this->tokenFor($this->clientUser, 'token-contrato-me');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me');

        $response->assertOk();
        $this->assertMatchesContract('auth-me.200.json', $response->json());
    }

    public function test_appointments_index_for_staff_matches_the_documented_contract(): void
    {
        $this->appointmentFor($this->clientProfile, now()->addDays(5)->format('Y-m-d'));
        $token = $this->tokenFor($this->adminUser, 'token-contrato-index-admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/appointments');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertMatchesContract('appointments-index.200.json', $response->json());
    }

    public function test_appointments_index_for_client_matches_the_documented_contract(): void
    {
        $this->appointmentFor($this->clientProfile, now()->addDays(5)->format('Y-m-d'));
        $token = $this->tokenFor($this->clientUser, 'token-contrato-index-cliente');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/appointments');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertNotNull($response->json('next'));
        $this->assertMatchesContract('appointments-index-cliente.200.json', $response->json());
    }

    public function test_appointments_store_matches_the_documented_contract(): void
    {
        $product = Product::create([
            'nombre' => 'Cera mate', 'categoria' => 'cuidado', 'descripcion' => 'Test',
            'precio_compra' => 80, 'precio_venta' => 120, 'stock_actual' => 5, 'stock_minimo' => 2,
            'tipo' => Product::TYPE_SALE, 'activo' => true,
        ]);
        $token = $this->tokenFor($this->adminUser, 'token-contrato-store');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/appointments', [
            'client_id' => (string) $this->clientProfile->id,
            'barber_id' => (string) $this->barber->id,
            'service_id' => (string) $this->service->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '16:00',
            'productos' => [['product_id' => (string) $product->id, 'cantidad' => 1]],
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('productos_agregados'), 'El caso debe ejercitar productos_agregados.');
        $this->assertMatchesContract('appointments-store.201.json', $response->json());
    }

    public function test_the_comparator_detects_an_undocumented_key_and_a_wrong_type(): void
    {
        // Sin esto, un comparador que siempre pasa dejaría la suite en verde
        // mientras el contrato se pudre: probamos que sí falla cuando debe.
        $documented = ['id' => 'abc', 'items' => [['n' => 1]]];

        $this->expectContractFailure(fn () => $this->compareStructure($documented, ['id' => 'abc', 'items' => [['n' => 1]], 'extra' => true], '$'));
        $this->expectContractFailure(fn () => $this->compareStructure($documented, ['id' => 7, 'items' => [['n' => 1]]], '$'));
        $this->expectContractFailure(fn () => $this->compareStructure($documented, ['id' => 'abc', 'items' => [['n' => 'uno']]], '$'));
        $this->expectContractFailure(fn () => $this->compareStructure($documented, ['items' => [['n' => 1]]], '$'));

        // null es comodín en ambos lados, y las listas vacías no tienen elementos que comparar.
        $this->compareStructure($documented, ['id' => null, 'items' => []], '$');
        $this->compareStructure(['id' => null], ['id' => 'abc'], '$');
    }

    private function expectContractFailure(callable $comparison): void
    {
        try {
            $comparison();
        } catch (AssertionFailedError) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('El comparador debía rechazar esta respuesta.');
    }

    private function assertMatchesContract(string $file, mixed $actual): void
    {
        $example = json_decode(
            (string) file_get_contents(base_path("docs/contrato/{$file}")),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->compareStructure($example, $actual, $file);
    }

    private function compareStructure(mixed $expected, mixed $actual, string $at): void
    {
        if ($expected === null || $actual === null) {
            return;
        }

        $this->assertSame($this->jsonKind($expected), $this->jsonKind($actual), "{$at}: el tipo documentado no coincide con la respuesta real.");

        if ($this->jsonKind($expected) === 'object') {
            $this->assertEqualsCanonicalizing(array_keys($expected), array_keys($actual), "{$at}: las claves documentadas no coinciden con las de la respuesta real.");
            foreach ($expected as $key => $value) {
                $this->compareStructure($value, $actual[$key], "{$at}.{$key}");
            }
        } elseif ($this->jsonKind($expected) === 'list' && $expected !== []) {
            foreach ($actual as $index => $item) {
                $this->compareStructure($expected[0], $item, "{$at}[{$index}]");
            }
        }
    }

    private function jsonKind(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) => 'list',
            is_array($value) => 'object',
            default => 'unknown',
        };
    }
}
