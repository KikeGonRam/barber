<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Inventory\InventoryLowStockNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre la pieza de
 * seguimiento de "ya pedido": un producto marcado como pedido no debe
 * reaparecer en la alerta diaria mientras esté dentro de
 * Product::RESTOCK_GRACE_DAYS, pero sí debe reaparecer si el pedido nunca
 * llegó (stock sigue bajo) pasado ese plazo.
 */
class SendLowStockAlertCommandTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $this->admin = User::create([
            'name' => 'Admin de prueba',
            'email' => 'admin-lowstock@test.local',
            'password' => 'password',
        ]);
        $this->admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
    }

    protected function tearDown(): void
    {
        Product::withTrashed()->get()->each(fn (Product $p) => $p->forceDelete());
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeLowStockProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'nombre' => 'Producto bajo stock',
            'categoria' => 'cuidado',
            'stock_actual' => 1,
            'stock_minimo' => 5,
            'precio_venta' => 100,
            'activo' => true,
        ], $overrides));
    }

    public function test_notifies_staff_about_a_low_stock_product_with_no_pending_order(): void
    {
        Notification::fake();
        $this->makeLowStockProduct();

        $this->artisan('inventory:low-stock-alert')->assertExitCode(0);

        Notification::assertSentTo($this->admin, InventoryLowStockNotification::class);
    }

    public function test_suppresses_the_alert_for_a_product_marked_as_ordered_within_the_grace_window(): void
    {
        Notification::fake();
        $this->makeLowStockProduct(['reabastecimiento_pedido_en' => now()->subDays(2)]);

        $this->artisan('inventory:low-stock-alert')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_reactivates_the_alert_once_the_grace_window_expires(): void
    {
        Notification::fake();
        $this->makeLowStockProduct([
            'reabastecimiento_pedido_en' => now()->subDays(Product::RESTOCK_GRACE_DAYS + 1),
        ]);

        $this->artisan('inventory:low-stock-alert')->assertExitCode(0);

        Notification::assertSentTo($this->admin, InventoryLowStockNotification::class);
    }
}
