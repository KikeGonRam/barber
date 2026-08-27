<?php

namespace Tests\Feature;

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Services\Order\OrderService;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Cubre place() (valida stock de TODOS
 * los items antes de descontar cualquiera, descuenta con trazabilidad vía
 * InventoryService, calcula el total) y cancel() (devuelve stock solo si
 * el pedido sigue pendiente, tolera productos ya eliminados).
 */
class OrderServiceIntegrationTest extends TestCase
{
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderService::class);
    }

    protected function tearDown(): void
    {
        Order::query()->delete();
        InventoryMovement::query()->delete();
        Product::query()->delete();
        Client::query()->delete();

        parent::tearDown();
    }

    private function makeClient(): Client
    {
        return Client::create([
            'user_id' => (string) Str::uuid(),
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

    public function test_place_creates_the_order_and_decrements_stock_for_each_item(): void
    {
        $client = $this->makeClient();
        $productA = $this->makeProduct(['nombre' => 'Cera', 'precio_venta' => 100, 'stock_actual' => 10]);
        $productB = $this->makeProduct(['nombre' => 'Shampoo', 'precio_venta' => 50, 'stock_actual' => 5]);

        $order = $this->service->place($client, [
            ['product_id' => (string) $productA->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 2],
            ['product_id' => (string) $productB->id, 'nombre' => 'Shampoo', 'precio' => 50, 'cantidad' => 1],
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('pendiente', $order->estado);
        $this->assertSame('tienda', $order->tipo);
        $this->assertStringStartsWith('P-', $order->folio);
        $this->assertEquals(250.0, (float) $order->total); // 2*100 + 1*50

        $this->assertSame(8, Product::find($productA->id)->stock_actual);
        $this->assertSame(4, Product::find($productB->id)->stock_actual);
    }

    public function test_place_throws_when_all_items_have_zero_or_negative_quantity(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct();

        $this->expectException(\RuntimeException::class);

        $this->service->place($client, [
            ['product_id' => (string) $product->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 0],
        ]);
    }

    public function test_place_throws_when_a_product_is_not_sellable(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(['activo' => false]);

        $this->expectException(\RuntimeException::class);

        $this->service->place($client, [
            ['product_id' => (string) $product->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 1],
        ]);
    }

    public function test_place_validates_stock_of_every_item_before_touching_any_stock(): void
    {
        $client = $this->makeClient();
        $productOk = $this->makeProduct(['nombre' => 'Cera', 'stock_actual' => 10]);
        $productShort = $this->makeProduct(['nombre' => 'Shampoo', 'stock_actual' => 1]);

        try {
            $this->service->place($client, [
                ['product_id' => (string) $productOk->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 2],
                ['product_id' => (string) $productShort->id, 'nombre' => 'Shampoo', 'precio' => 50, 'cantidad' => 5],
            ]);
            $this->fail('Se esperaba InsufficientStockException.');
        } catch (InsufficientStockException) {
            // El primer producto (válido) no debe haber perdido stock: la
            // validación corre para TODOS los items antes de descontar cualquiera.
            $this->assertSame(10, Product::find($productOk->id)->stock_actual);
            $this->assertSame(1, Product::find($productShort->id)->stock_actual);
            $this->assertSame(0, Order::query()->count());
        }
    }

    public function test_place_ignores_lines_with_zero_quantity_but_keeps_valid_ones(): void
    {
        $client = $this->makeClient();
        $productA = $this->makeProduct(['nombre' => 'Cera']);
        $productB = $this->makeProduct(['nombre' => 'Shampoo']);

        $order = $this->service->place($client, [
            ['product_id' => (string) $productA->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 1],
            ['product_id' => (string) $productB->id, 'nombre' => 'Shampoo', 'precio' => 50, 'cantidad' => 0],
        ]);

        $this->assertCount(1, $order->items);
        $this->assertSame((string) $productA->id, $order->items[0]['product_id']);
    }

    public function test_cancel_reverts_stock_and_marks_the_order_cancelado(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->service->place($client, [
            ['product_id' => (string) $product->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 3],
        ]);
        $this->assertSame(7, Product::find($product->id)->stock_actual);

        $this->service->cancel($order->fresh());

        $this->assertSame(10, Product::find($product->id)->stock_actual);
        $this->assertSame('cancelado', Order::find($order->id)->estado);
    }

    public function test_cancel_is_a_no_op_for_an_order_that_is_not_pending(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->service->place($client, [
            ['product_id' => (string) $product->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 3],
        ]);
        $order->update(['estado' => 'entregado']);

        $this->service->cancel($order->fresh());

        // No debe devolver stock ni tocar el estado ya asentado.
        $this->assertSame(7, Product::find($product->id)->stock_actual);
        $this->assertSame('entregado', Order::find($order->id)->estado);
    }

    public function test_cancel_still_marks_cancelado_even_if_a_product_line_was_deleted(): void
    {
        $client = $this->makeClient();
        $product = $this->makeProduct(['stock_actual' => 10]);

        $order = $this->service->place($client, [
            ['product_id' => (string) $product->id, 'nombre' => 'Cera', 'precio' => 100, 'cantidad' => 2],
        ]);

        $product->delete();

        $this->service->cancel($order->fresh());

        $this->assertSame('cancelado', Order::find($order->id)->estado);
    }
}
