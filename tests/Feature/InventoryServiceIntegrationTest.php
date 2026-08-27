<?php

namespace Tests\Feature;

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Cubre normalización de payload legado,
 * el borrado de la imagen asociada al eliminar un producto, el conteo de
 * stock bajo, y registerMovement() (entradas/salidas, stock insuficiente,
 * atomicidad vía DB::transaction()).
 */
class InventoryServiceIntegrationTest extends TestCase
{
    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryService::class);
    }

    protected function tearDown(): void
    {
        InventoryMovement::query()->delete();
        Product::withTrashed()->get()->each(fn (Product $p) => $p->forceDelete());

        parent::tearDown();
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'nombre' => 'Cera para cabello',
            'categoria' => 'cuidado',
            'precio_compra' => 50,
            'precio_venta' => 100,
            'stock_actual' => 10,
            'stock_minimo' => 5,
            'tipo' => Product::TYPE_SALE,
            'activo' => true,
        ], $overrides));
    }

    public function test_create_product_normalizes_legacy_type_and_active_alias(): void
    {
        $product = $this->service->createProduct([
            'nombre' => 'Insumo legado',
            'tipo' => Product::LEGACY_TYPE_SUPPLY,
            'active' => false,
            'stock_actual' => 3,
            'stock_minimo' => 1,
        ]);

        $this->assertSame(Product::TYPE_SUPPLY, $product->tipo);
        $this->assertFalse($product->activo);

        $fresh = Product::find($product->id);
        $this->assertSame(Product::TYPE_SUPPLY, $fresh->tipo);
        $this->assertFalse($fresh->activo);
    }

    public function test_update_product_normalizes_payload_before_persisting(): void
    {
        $product = $this->makeProduct(['tipo' => Product::TYPE_SALE]);

        $result = $this->service->updateProduct($product, [
            'tipo' => Product::LEGACY_TYPE_SUPPLY,
            'active' => false,
        ]);

        $this->assertTrue($result);

        $fresh = Product::find($product->id);
        $this->assertSame(Product::TYPE_SUPPLY, $fresh->tipo);
        $this->assertFalse($fresh->activo);
    }

    public function test_delete_product_removes_the_associated_image_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/foto.jpg', 'contenido-falso');

        $product = $this->makeProduct(['imagen' => 'products/foto.jpg']);

        $result = $this->service->deleteProduct($product);

        $this->assertTrue($result);
        Storage::disk('public')->assertMissing('products/foto.jpg');

        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    public function test_delete_product_without_image_does_not_error(): void
    {
        $product = $this->makeProduct(['imagen' => null]);

        $result = $this->service->deleteProduct($product);

        $this->assertTrue($result);
        $this->assertNull(Product::find($product->id));
    }

    public function test_low_stock_count_only_counts_products_at_or_below_minimum(): void
    {
        $this->makeProduct(['nombre' => 'Bajo stock (igual al minimo)', 'stock_actual' => 5, 'stock_minimo' => 5]);
        $this->makeProduct(['nombre' => 'Bajo stock (por debajo)', 'stock_actual' => 1, 'stock_minimo' => 5]);
        $this->makeProduct(['nombre' => 'Stock suficiente', 'stock_actual' => 20, 'stock_minimo' => 5]);

        $this->assertSame(2, $this->service->lowStockCount());
    }

    public function test_register_movement_entrada_increments_stock_and_creates_record(): void
    {
        $product = $this->makeProduct(['stock_actual' => 10]);
        $userId = (string) Str::uuid();

        $movement = $this->service->registerMovement([
            'product_id' => (string) $product->id,
            'tipo' => 'entrada',
            'cantidad' => 5,
            'motivo' => 'Reabastecimiento',
        ], $userId);

        $this->assertInstanceOf(InventoryMovement::class, $movement);
        $this->assertSame(15, Product::find($product->id)->stock_actual);
        $this->assertSame($userId, $movement->user_id);
        $this->assertSame('entrada', $movement->tipo);
        $this->assertSame(5, $movement->cantidad);
    }

    public function test_register_movement_salida_decrements_stock(): void
    {
        $product = $this->makeProduct(['stock_actual' => 10]);

        $this->service->registerMovement([
            'product_id' => (string) $product->id,
            'tipo' => 'salida',
            'cantidad' => 4,
        ], (string) Str::uuid());

        $this->assertSame(6, Product::find($product->id)->stock_actual);
    }

    public function test_register_movement_salida_throws_when_stock_is_insufficient(): void
    {
        $product = $this->makeProduct(['stock_actual' => 3]);

        $this->expectException(InsufficientStockException::class);

        try {
            $this->service->registerMovement([
                'product_id' => (string) $product->id,
                'tipo' => 'salida',
                'cantidad' => 5,
            ], (string) Str::uuid());
        } finally {
            // El stock no debe haberse tocado y no debe haber quedado
            // ningún movimiento a medias (DB::transaction hace rollback).
            $this->assertSame(3, Product::find($product->id)->stock_actual);
            $this->assertSame(0, InventoryMovement::query()->where('product_id', (string) $product->id)->count());
        }
    }

    public function test_list_products_filters_by_categoria_and_tipo(): void
    {
        $this->makeProduct(['nombre' => 'Cera', 'categoria' => 'cuidado', 'tipo' => Product::TYPE_SALE]);
        $this->makeProduct(['nombre' => 'Guantes', 'categoria' => 'insumos', 'tipo' => Product::TYPE_SUPPLY]);
        $this->makeProduct(['nombre' => 'Shampoo', 'categoria' => 'cuidado', 'tipo' => Product::TYPE_SUPPLY]);

        $cuidado = $this->service->listProducts(['categoria' => 'cuidado']);
        $this->assertSame(2, $cuidado->total());

        $insumos = $this->service->listProducts(['tipo' => Product::TYPE_SUPPLY]);
        $this->assertSame(2, $insumos->total());
    }

    public function test_list_movements_filters_by_tipo_and_product(): void
    {
        $productA = $this->makeProduct(['nombre' => 'Producto A']);
        $productB = $this->makeProduct(['nombre' => 'Producto B']);
        $userId = (string) Str::uuid();

        $this->service->registerMovement(['product_id' => (string) $productA->id, 'tipo' => 'entrada', 'cantidad' => 2], $userId);
        $this->service->registerMovement(['product_id' => (string) $productA->id, 'tipo' => 'salida', 'cantidad' => 1], $userId);
        $this->service->registerMovement(['product_id' => (string) $productB->id, 'tipo' => 'entrada', 'cantidad' => 3], $userId);

        $entradas = $this->service->listMovements(['tipo' => 'entrada']);
        $this->assertSame(2, $entradas->total());

        $deProductoA = $this->service->listMovements(['product_id' => (string) $productA->id]);
        $this->assertSame(2, $deProductoA->total());
    }
}
