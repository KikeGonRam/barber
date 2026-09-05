<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\Order\OrderExpiredNotification;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre que
 * orders:cancel-expired encuentra pedidos "pendiente" (paga y recoge en
 * sucursal) abandonados por más de CancelExpiredOrdersCommand::DIAS_ABANDONO
 * días, los cancela devolviendo el stock reservado vía OrderService::cancel(),
 * avisa al cliente, y deja intactos los pedidos recientes o ya resueltos.
 */
class CancelExpiredOrdersCommandTest extends TestCase
{
    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
    }

    protected function tearDown(): void
    {
        Order::query()->delete();
        InventoryMovement::query()->delete();
        Product::query()->delete();
        Client::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    private function makeClientWithUser(): Client
    {
        $user = User::create(['name' => 'Cliente Pedido', 'email' => uniqid().'@test.local', 'password' => 'password']);

        return Client::create([
            'user_id' => (string) $user->id,
            'telefono' => '5551234567',
            'nivel' => 'nuevo',
            'puntos' => 0,
            'total_citas' => 0,
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'nombre' => 'Cera para cabello',
            'precio_venta' => 100,
            'stock_actual' => 10,
            'tipo' => Product::TYPE_SALE,
            'activo' => true,
        ], $overrides));
    }

    public function test_cancels_a_pending_order_abandoned_past_the_threshold_and_returns_stock(): void
    {
        Notification::fake();

        $client = $this->makeClientWithUser();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->orderService->place($client, [
            ['product_id' => (string) $product->id, 'cantidad' => 3],
        ]);
        $order->forceFill(['created_at' => now()->subDays(5)])->save();
        $this->assertSame(7, Product::find($product->id)->stock_actual);

        $this->artisan('orders:cancel-expired')->assertExitCode(0);

        $this->assertSame(10, Product::find($product->id)->stock_actual);
        $this->assertSame('cancelado', Order::find($order->id)->estado);
        Notification::assertSentTo($client->user, OrderExpiredNotification::class);
    }

    public function test_dry_run_does_not_cancel_or_notify(): void
    {
        Notification::fake();

        $client = $this->makeClientWithUser();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->orderService->place($client, [
            ['product_id' => (string) $product->id, 'cantidad' => 3],
        ]);
        $order->forceFill(['created_at' => now()->subDays(5)])->save();

        $this->artisan('orders:cancel-expired', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(7, Product::find($product->id)->stock_actual);
        $this->assertSame('pendiente', Order::find($order->id)->estado);
        Notification::assertNothingSent();
    }

    public function test_leaves_recent_pending_orders_untouched(): void
    {
        Notification::fake();

        $client = $this->makeClientWithUser();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->orderService->place($client, [
            ['product_id' => (string) $product->id, 'cantidad' => 3],
        ]);
        // Sin forzar created_at: el pedido es "de hoy", dentro del plazo.

        $this->artisan('orders:cancel-expired')->assertExitCode(0);

        $this->assertSame(7, Product::find($product->id)->stock_actual);
        $this->assertSame('pendiente', Order::find($order->id)->estado);
        Notification::assertNothingSent();
    }

    public function test_leaves_already_delivered_orders_untouched_even_if_old(): void
    {
        Notification::fake();

        $client = $this->makeClientWithUser();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->orderService->place($client, [
            ['product_id' => (string) $product->id, 'cantidad' => 3],
        ]);
        $order->forceFill(['estado' => 'entregado', 'created_at' => now()->subDays(10)])->save();

        $this->artisan('orders:cancel-expired')->assertExitCode(0);

        $this->assertSame(7, Product::find($product->id)->stock_actual);
        $this->assertSame('entregado', Order::find($order->id)->estado);
        Notification::assertNothingSent();
    }
}
