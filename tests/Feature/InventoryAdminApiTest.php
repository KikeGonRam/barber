<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas.
 * App\Http\Controllers\Api\Admin\Inventory\InventoryAdminController
 * (admin/inventory/*) -- distinto de Api\Inventory\InventoryController
 * (ya cubierto por InventoryApiTest): este es el CRUD completo de
 * productos + movimientos para el panel admin, hasta ahora solo con el
 * 403 cubierto (EngineerRoleAuthorizationTest).
 */
class InventoryAdminApiTest extends TestCase
{
    private string $adminToken = 'test-inventory-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Inventario', 'email' => 'admin-inventory-admin@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
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

    private function product(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'nombre' => 'Cera Modeladora', 'categoria' => 'cuidado', 'descripcion' => 'Test',
            'precio_compra' => 80, 'precio_venta' => 150, 'stock_actual' => 10, 'stock_minimo' => 5,
            'tipo' => Product::TYPE_SALE, 'activo' => true,
        ], $overrides));
    }

    public function test_get_products_lists_with_search_and_category_filter(): void
    {
        $this->product(['nombre' => 'Cera Modeladora', 'categoria' => 'cuidado']);
        $this->product(['nombre' => 'Tijeras Pro', 'categoria' => 'herramientas']);

        $bySearch = $this->withToken($this->adminToken)->getJson('/api/v1/admin/inventory/products?search=Cera');
        $bySearch->assertOk()->assertJsonCount(1, 'data');

        $byCategory = $this->withToken($this->adminToken)->getJson('/api/v1/admin/inventory/products?category=herramientas');
        $byCategory->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Tijeras Pro');
    }

    public function test_show_returns_product_with_consumption_and_movements(): void
    {
        $product = $this->product(['stock_actual' => 10]);
        InventoryMovement::create([
            'product_id' => (string) $product->id, 'tipo' => 'salida', 'cantidad' => 3,
            'motivo' => 'Venta', 'user_id' => (string) Str::uuid(), 'fecha' => now(),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/inventory/products/'.$product->id);

        $response->assertOk();
        $response->assertJsonPath('data.monthlyConsumption', 3);
        $response->assertJsonCount(1, 'data.movements');
    }

    public function test_store_creates_a_product(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/v1/admin/inventory/products', [
            'nombre' => 'Producto Nuevo',
            'categoria' => 'cuidado',
            'stock_actual' => 20,
            'stock_minimo' => 5,
            'precio_venta' => 199,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.nombre', 'Producto Nuevo');
        $this->assertSame(1, Product::where('nombre', 'Producto Nuevo')->count());
    }

    public function test_store_requires_core_fields(): void
    {
        $response = $this->withToken($this->adminToken)->postJson('/api/v1/admin/inventory/products', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nombre', 'categoria', 'stock_actual', 'stock_minimo', 'precio_venta']);
    }

    public function test_update_persists_changes(): void
    {
        $product = $this->product(['nombre' => 'Nombre Viejo']);

        $response = $this->withToken($this->adminToken)->putJson('/api/v1/admin/inventory/products/'.$product->id, [
            'nombre' => 'Nombre Nuevo',
        ]);

        $response->assertOk();
        $this->assertSame('Nombre Nuevo', $product->fresh()->nombre);
    }

    public function test_destroy_deletes_the_product(): void
    {
        $product = $this->product();

        $response = $this->withToken($this->adminToken)->deleteJson('/api/v1/admin/inventory/products/'.$product->id);

        $response->assertOk();
        $this->assertNull(Product::find($product->id));
    }

    public function test_record_movement_entrada_increments_stock(): void
    {
        $product = $this->product(['stock_actual' => 10]);

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/admin/inventory/products/'.$product->id.'/movement', [
            'tipo' => 'entrada', 'cantidad' => 5, 'motivo' => 'Reposición',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stock_actual', 15);
        $this->assertSame(1, InventoryMovement::where('product_id', (string) $product->id)->count());
    }

    public function test_record_movement_salida_rejects_insufficient_stock(): void
    {
        $product = $this->product(['stock_actual' => 2]);

        $response = $this->withToken($this->adminToken)->postJson('/api/v1/admin/inventory/products/'.$product->id.'/movement', [
            'tipo' => 'salida', 'cantidad' => 5, 'motivo' => 'Venta',
        ]);

        $response->assertStatus(422);
        $this->assertSame(2, $product->fresh()->stock_actual);
    }

    public function test_get_movements_lists_within_default_date_range(): void
    {
        $product = $this->product();
        InventoryMovement::create([
            'product_id' => (string) $product->id, 'tipo' => 'entrada', 'cantidad' => 10,
            'motivo' => 'Compra', 'user_id' => (string) Str::uuid(), 'fecha' => now(),
        ]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/inventory/movements');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.product', $product->nombre);
    }

    public function test_summary_reports_totals_and_low_stock_counts(): void
    {
        $this->product(['nombre' => 'Producto Bajo', 'stock_actual' => 1, 'stock_minimo' => 5, 'precio_venta' => 100]);
        $this->product(['nombre' => 'Producto Ok', 'stock_actual' => 20, 'stock_minimo' => 5, 'precio_venta' => 50]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/inventory/summary');

        $response->assertOk();
        $response->assertJsonPath('data.totalProducts', 2);
        $response->assertJsonPath('data.lowStockCount', 1);
    }

    public function test_low_stock_products_lists_only_products_at_or_below_minimum(): void
    {
        $this->product(['nombre' => 'Bajo Stock', 'stock_actual' => 2, 'stock_minimo' => 5]);
        $this->product(['nombre' => 'Stock Suficiente', 'stock_actual' => 20, 'stock_minimo' => 5]);

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/admin/inventory/low-stock');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nombre', 'Bajo Stock');
    }

    public function test_non_admin_cannot_reach_inventory_admin_endpoints(): void
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $barbero = User::create(['name' => 'Barbero Test', 'email' => 'barbero-inv-admin@test.local', 'password' => 'password']);
        $barbero->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        $token = 'test-non-admin-inventory-token';
        MobileApiToken::create(['user_id' => (string) $barbero->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/admin/inventory/products')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/inventory/summary')->assertForbidden();
    }
}
