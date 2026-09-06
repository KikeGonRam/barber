<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el módulo de
 * Inventario del frontend Nuxt (ver frontend-urban/.claude/skills/
 * nuxt-migration-plan/SKILL.md, Fase 9.5): catálogo de productos (CRUD
 * admin-only), movimientos de stock (staff), panel de bajo stock y
 * "marcar como pedido" — puerto de Inventory\ProductController +
 * Inventory\InventoryMovementController (web), enriquecidos en esta
 * misma fase con filtros/stats que la API todavía no tenía.
 */
class InventoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        InventoryMovement::query()->delete();
        Product::withTrashed()->forceDelete();
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
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    private function staffUser(string $roleName, string $email): User
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName).' Inventory', 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        return $user;
    }

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'nombre' => 'Cera Modeladora', 'categoria' => 'cuidado', 'descripcion' => 'Test',
            'precio_compra' => 80, 'precio_venta' => 150, 'stock_actual' => 10, 'stock_minimo' => 5,
            'tipo' => Product::TYPE_SALE, 'activo' => true,
        ], $overrides));
    }

    public function test_recepcionista_can_list_products_with_stats_and_filters(): void
    {
        $staff = $this->staffUser('recepcionista', 'recepcion-inventory@test.local');
        $this->product(['nombre' => 'Cera', 'precio_compra' => 80, 'precio_venta' => 150, 'stock_actual' => 10, 'stock_minimo' => 5]);
        $this->product(['nombre' => 'Shampoo', 'precio_compra' => 40, 'precio_venta' => 90, 'stock_actual' => 1, 'stock_minimo' => 5]);

        $token = $this->tokenFor($staff, 'test-plaintext-token-inventory-list');

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/products');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => ['*' => ['id', 'nombre', 'stock_actual', 'precio_compra', 'precio_venta', 'low_stock', 'pending_restock']],
            'meta' => ['total', 'stats' => ['total', 'bajo_stock', 'valor_total'], 'categorias', 'tipos'],
        ]);
        // precio_compra/precio_venta usan cast decimal:2 (serializan como string en
        // el modelo) — deben llegar como number real ("150", no "150.00" con
        // comillas) para que el frontend no repita el bug de "$NaN" de las
        // fases 9.3/9.4. assertJsonPath compara con === contra el 150 (int) de
        // abajo, así que una string "150.00" haría fallar esta aserción.
        $response->assertJsonPath('data.0.precio_venta', 150);
        $response->assertJsonPath('meta.stats.total', 2);
        $response->assertJsonPath('meta.stats.bajo_stock', 1);

        $filtered = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/products?bajo_stock=1');
        $filtered->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.nombre', 'Shampoo');
    }

    public function test_only_admin_can_create_update_or_delete_products(): void
    {
        $recepcion = $this->staffUser('recepcionista', 'recepcion-inventory-crud@test.local');
        $tokenRecepcion = $this->tokenFor($recepcion, 'test-plaintext-token-inventory-crud-recepcion');

        $create = $this->withHeader('Authorization', "Bearer {$tokenRecepcion}")
            ->postJson('/api/v1/inventory/products', [
                'nombre' => 'Nuevo', 'categoria' => 'cuidado', 'precio_compra' => 10, 'precio_venta' => 20,
                'stock_actual' => 5, 'stock_minimo' => 1, 'tipo' => Product::TYPE_SALE,
            ]);
        $create->assertForbidden();

        $admin = $this->staffUser('administrador', 'admin-inventory-crud@test.local');
        $tokenAdmin = $this->tokenFor($admin, 'test-plaintext-token-inventory-crud-admin');

        $created = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->postJson('/api/v1/inventory/products', [
                'nombre' => 'Nuevo Producto', 'categoria' => 'cuidado', 'precio_compra' => 10, 'precio_venta' => 20,
                'stock_actual' => 5, 'stock_minimo' => 1, 'tipo' => Product::TYPE_SALE,
            ]);
        $created->assertCreated();
        $productId = $created->json('data.id');

        $updated = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->putJson("/api/v1/inventory/products/{$productId}", ['precio_venta' => 25]);
        $updated->assertOk();
        $updated->assertJsonPath('data.precio_venta', 25);

        $deleted = $this->withHeader('Authorization', "Bearer {$tokenRecepcion}")
            ->deleteJson("/api/v1/inventory/products/{$productId}");
        $deleted->assertForbidden();

        $deleted = $this->withHeader('Authorization', "Bearer {$tokenAdmin}")
            ->deleteJson("/api/v1/inventory/products/{$productId}");
        $deleted->assertOk();
        $this->assertNull(Product::find($productId));
    }

    public function test_recepcionista_can_only_register_salida_movements(): void
    {
        $product = $this->product(['stock_actual' => 10]);
        $staff = $this->staffUser('recepcionista', 'recepcion-inventory-movement@test.local');
        $token = $this->tokenFor($staff, 'test-plaintext-token-inventory-movement');

        $entrada = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/movements', ['product_id' => (string) $product->id, 'tipo' => 'entrada', 'cantidad' => 5]);
        $entrada->assertStatus(422);

        $salida = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/movements', ['product_id' => (string) $product->id, 'tipo' => 'salida', 'cantidad' => 3, 'motivo' => 'Consumo']);
        $salida->assertCreated();
        $this->assertSame(7, $product->fresh()->stock_actual);

        $excesiva = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/movements', ['product_id' => (string) $product->id, 'tipo' => 'salida', 'cantidad' => 999]);
        $excesiva->assertStatus(422);
    }

    public function test_admin_can_register_entrada_and_it_clears_pending_restock(): void
    {
        $product = $this->product(['stock_actual' => 2, 'stock_minimo' => 5, 'reabastecimiento_pedido_en' => now()]);
        $admin = $this->staffUser('administrador', 'admin-inventory-entrada@test.local');
        $token = $this->tokenFor($admin, 'test-plaintext-token-inventory-entrada');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/movements', ['product_id' => (string) $product->id, 'tipo' => 'entrada', 'cantidad' => 20, 'motivo' => 'Reposición']);

        $response->assertCreated();
        $fresh = $product->fresh();
        $this->assertSame(22, $fresh->stock_actual);
        $this->assertNull($fresh->reabastecimiento_pedido_en);
    }

    public function test_movements_list_supports_filters_and_stats(): void
    {
        $product = $this->product(['stock_actual' => 20]);
        $staff = $this->staffUser('administrador', 'admin-inventory-movements-list@test.local');
        $token = $this->tokenFor($staff, 'test-plaintext-token-inventory-movements-list');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/movements', ['product_id' => (string) $product->id, 'tipo' => 'salida', 'cantidad' => 2, 'motivo' => 'Consumo cita']);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/inventory/movements', ['product_id' => (string) $product->id, 'tipo' => 'entrada', 'cantidad' => 10, 'motivo' => 'Reposición']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/movements');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.stats.total', 2);
        $response->assertJsonPath('meta.stats.entradas', 1);
        $response->assertJsonPath('meta.stats.salidas', 1);

        $onlyEntradas = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/movements?tipo=entrada');
        $onlyEntradas->assertJsonCount(1, 'data');
        $onlyEntradas->assertJsonPath('data.0.tipo', 'entrada');
    }

    public function test_low_stock_panel_and_mark_as_ordered(): void
    {
        $product = $this->product(['stock_actual' => 1, 'stock_minimo' => 5]);
        $staff = $this->staffUser('recepcionista', 'recepcion-inventory-lowstock@test.local');
        $token = $this->tokenFor($staff, 'test-plaintext-token-inventory-lowstock');

        $lowStock = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/low-stock');
        $lowStock->assertOk();
        $lowStock->assertJsonCount(1, 'data');
        $lowStock->assertJsonPath('data.0.pending_restock', false);

        $mark = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/inventory/products/{$product->id}/mark-ordered");
        $mark->assertOk();
        $this->assertNotNull($product->fresh()->reabastecimiento_pedido_en);

        $lowStockAfter = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/low-stock');
        $lowStockAfter->assertJsonPath('data.0.pending_restock', true);
    }

    public function test_cliente_cannot_access_inventory(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente Inventory', 'email' => 'cliente-inventory@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = $this->tokenFor($user, 'test-plaintext-token-inventory-guard');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/products')->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/movements')->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/inventory/low-stock')->assertForbidden();
    }
}
