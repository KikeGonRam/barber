<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el módulo de
 * Pedidos del frontend Nuxt (ver frontend-urban/.claude/skills/
 * nuxt-migration-plan/SKILL.md, Fase 9.4): catálogo público de tienda
 * (GET /products), autoservicio de cliente (crear/ver/cancelar sus
 * pedidos) y bandeja de recepción/administración (ver todos, entregar,
 * recibo) — puerto de Client\StoreController/CartController/OrderController
 * + Reception\OrderController (web), que antes solo existían como sesión +
 * carrito en sesión de servidor (incompatible con un cliente Bearer-token
 * sin sesión, de ahi que el carrito real viva en el frontend Nuxt).
 */
class OrderApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Order::query()->delete();
        Product::query()->delete();
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

    private function clientUser(string $email): array
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Orders', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $client = Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        return [$user, $client];
    }

    private function staffUser(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Orders', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    private function sellableProduct(string $nombre = 'Cera Modeladora', float $precio = 150, int $stock = 10): Product
    {
        return Product::create([
            'nombre' => $nombre, 'categoria' => 'cuidado', 'descripcion' => 'Test',
            'precio_compra' => 80, 'precio_venta' => $precio, 'stock_actual' => $stock, 'stock_minimo' => 2,
            'tipo' => Product::TYPE_SALE, 'activo' => true,
        ]);
    }

    public function test_public_catalog_lists_only_sellable_products(): void
    {
        $this->sellableProduct('Cera', 150, 10);
        Product::create([
            'nombre' => 'Sin stock', 'categoria' => 'cuidado', 'descripcion' => 'Test',
            'precio_compra' => 80, 'precio_venta' => 150, 'stock_actual' => 0, 'stock_minimo' => 2,
            'tipo' => Product::TYPE_SALE, 'activo' => true,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nombre', 'Cera');
        $response->assertJsonPath('data.0.precio_venta', 150);
    }

    public function test_client_can_place_an_order_ignoring_a_manipulated_price(): void
    {
        [$user, $client] = $this->clientUser('cliente-orders@test.local');
        $product = $this->sellableProduct('Cera', 150, 10);

        $token = $this->tokenFor($user, 'test-plaintext-token-order-store');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/orders', [
                'items' => [
                    // 'precio' manipulado a $0.01: OrderService::place() debe ignorarlo
                    // y releer Product::precio_venta (guardrail #13).
                    ['product_id' => (string) $product->id, 'cantidad' => 2, 'precio' => 0.01],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total', 300);
        $response->assertJsonPath('data.items.0.precio', 150);
        $response->assertJsonPath('data.estado', 'pendiente');

        $this->assertSame(8, $product->fresh()->stock_actual);
        $this->assertSame(1, Order::where('client_id', (string) $client->id)->count());
    }

    public function test_client_cannot_order_more_than_available_stock(): void
    {
        [$user] = $this->clientUser('cliente-orders-stock@test.local');
        $product = $this->sellableProduct('Cera', 150, 1);

        $token = $this->tokenFor($user, 'test-plaintext-token-order-stock');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => (string) $product->id, 'cantidad' => 5]],
            ]);

        $response->assertStatus(422);
        $this->assertSame(1, $product->fresh()->stock_actual);
    }

    public function test_client_sees_only_their_own_orders_while_staff_sees_all_with_stats(): void
    {
        [$userA, $clientA] = $this->clientUser('cliente-a-orders@test.local');
        [, $clientB] = $this->clientUser('cliente-b-orders@test.local');

        Order::create(['client_id' => (string) $clientA->id, 'folio' => 'P-AAAAAA', 'items' => [], 'total' => 100, 'estado' => 'pendiente', 'tipo' => 'tienda']);
        Order::create(['client_id' => (string) $clientB->id, 'folio' => 'P-BBBBBB', 'items' => [], 'total' => 200, 'estado' => 'pendiente', 'tipo' => 'tienda']);

        $tokenA = $this->tokenFor($userA, 'test-plaintext-token-order-clienta');
        $clientResponse = $this->withHeader('Authorization', "Bearer {$tokenA}")->getJson('/api/v1/orders');
        $clientResponse->assertOk();
        $clientResponse->assertJsonCount(1, 'data');
        $clientResponse->assertJsonPath('data.0.folio', 'P-AAAAAA');
        $clientResponse->assertJsonMissingPath('meta.stats');

        $staff = $this->staffUser('recepcionista', 'recepcion-orders@test.local');
        $tokenStaff = $this->tokenFor($staff, 'test-plaintext-token-order-staff');
        $staffResponse = $this->withHeader('Authorization', "Bearer {$tokenStaff}")->getJson('/api/v1/orders');
        $staffResponse->assertOk();
        $staffResponse->assertJsonCount(2, 'data');
        $staffResponse->assertJsonPath('meta.stats.pendientes', 2);
        $staffResponse->assertJsonPath('meta.stats.por_cobrar', 300);
    }

    public function test_client_can_cancel_their_own_pending_order_and_stock_is_restored(): void
    {
        [$user, $client] = $this->clientUser('cliente-orders-cancel@test.local');
        $product = $this->sellableProduct('Cera', 100, 10);

        $token = $this->tokenFor($user, 'test-plaintext-token-order-cancel');
        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/orders', ['items' => [['product_id' => (string) $product->id, 'cantidad' => 3]]]);
        $orderId = $create->json('data.id');

        $this->assertSame(7, $product->fresh()->stock_actual);

        $cancel = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/orders/{$orderId}/cancel");

        $cancel->assertOk();
        $cancel->assertJsonPath('data.estado', 'cancelado');
        $this->assertSame(10, $product->fresh()->stock_actual);
        $this->assertSame((string) $client->id, Order::find($orderId)->client_id);
    }

    public function test_cliente_cannot_deliver_or_download_receipt(): void
    {
        [, $client] = $this->clientUser('cliente-orders-guard@test.local');
        $order = Order::create(['client_id' => (string) $client->id, 'folio' => 'P-GUARD1', 'items' => [], 'total' => 100, 'estado' => 'pendiente', 'tipo' => 'tienda']);

        [$user] = $this->clientUser('cliente-orders-guard2@test.local');
        $token = $this->tokenFor($user, 'test-plaintext-token-order-guard');

        $deliver = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/orders/{$order->id}/deliver", ['metodo_pago' => 'efectivo']);
        $deliver->assertForbidden();

        $receipt = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/orders/{$order->id}/receipt");
        $receipt->assertForbidden();
    }

    public function test_staff_can_deliver_a_pending_order(): void
    {
        [, $client] = $this->clientUser('cliente-orders-deliver@test.local');
        $order = Order::create(['client_id' => (string) $client->id, 'folio' => 'P-DELIVR', 'items' => [], 'total' => 250, 'estado' => 'pendiente', 'tipo' => 'tienda']);

        $staff = $this->staffUser('administrador', 'admin-orders-deliver@test.local');
        $token = $this->tokenFor($staff, 'test-plaintext-token-order-deliver');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/orders/{$order->id}/deliver", ['metodo_pago' => 'efectivo']);

        $response->assertOk();
        $response->assertJsonPath('data.estado', 'entregado');
        $response->assertJsonPath('data.metodo_pago', 'efectivo');
        $this->assertSame('entregado', $order->fresh()->estado);
    }
}
