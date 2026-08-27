<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Cart\CartService;
use Tests\TestCase;

/**
 * CartService guarda el carrito en sesión (no en Mongo), pero se prueba como
 * Feature test porque necesita el ciclo de vida real de Session que solo el
 * TestCase de Laravel arranca. Usa productos reales persistidos para que
 * add()/total() ejerzan las dos reglas de negocio documentadas en el
 * servicio: el precio se "congela" al agregar (no cambia si el producto se
 * edita después) y la cantidad se topa al stock disponible salvo que el
 * producto no tenga control de inventario (stock_actual = 0).
 */
class CartServiceIntegrationTest extends TestCase
{
    private CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cart = app(CartService::class);
    }

    protected function tearDown(): void
    {
        Product::query()->delete();

        parent::tearDown();
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'nombre' => 'Cera para cabello',
            'precio_venta' => 100,
            'stock_actual' => 5,
            'tipo' => Product::TYPE_SALE,
            'activo' => true,
        ], $overrides));
    }

    public function test_add_creates_a_new_line_snapshotting_name_and_price(): void
    {
        $product = $this->makeProduct(['nombre' => 'Pomada', 'precio_venta' => 150]);

        $this->cart->add($product, 2);

        $items = $this->cart->items();
        $this->assertCount(1, $items);
        $this->assertSame('Pomada', $items[(string) $product->id]['nombre']);
        $this->assertSame(150.0, $items[(string) $product->id]['precio']);
        $this->assertSame(2, $items[(string) $product->id]['cantidad']);
    }

    public function test_add_increments_quantity_of_an_existing_line(): void
    {
        $product = $this->makeProduct(['stock_actual' => 10]);

        $this->cart->add($product, 2);
        $this->cart->add($product, 3);

        $this->assertSame(5, $this->cart->items()[(string) $product->id]['cantidad']);
    }

    public function test_add_caps_quantity_at_available_stock(): void
    {
        $product = $this->makeProduct(['stock_actual' => 4]);

        $this->cart->add($product, 10);

        $this->assertSame(4, $this->cart->items()[(string) $product->id]['cantidad']);
    }

    public function test_add_does_not_cap_when_product_has_no_stock_control(): void
    {
        $product = $this->makeProduct(['stock_actual' => 0]);

        $this->cart->add($product, 10);

        $this->assertSame(10, $this->cart->items()[(string) $product->id]['cantidad']);
    }

    public function test_add_never_lets_quantity_drop_below_one(): void
    {
        $product = $this->makeProduct(['stock_actual' => 10]);

        $this->cart->add($product, 0);

        $this->assertSame(1, $this->cart->items()[(string) $product->id]['cantidad']);
    }

    public function test_update_changes_the_quantity_of_an_existing_line(): void
    {
        $product = $this->makeProduct();
        $this->cart->add($product, 1);

        $this->cart->update((string) $product->id, 3);

        $this->assertSame(3, $this->cart->items()[(string) $product->id]['cantidad']);
    }

    public function test_update_with_zero_or_less_removes_the_line(): void
    {
        $product = $this->makeProduct();
        $this->cart->add($product, 1);

        $this->cart->update((string) $product->id, 0);

        $this->assertArrayNotHasKey((string) $product->id, $this->cart->items());
    }

    public function test_update_on_an_unknown_product_is_a_no_op(): void
    {
        $this->cart->update('id-que-no-existe', 5);

        $this->assertSame([], $this->cart->items());
    }

    public function test_remove_deletes_the_line(): void
    {
        $product = $this->makeProduct();
        $this->cart->add($product, 1);

        $this->cart->remove((string) $product->id);

        $this->assertTrue($this->cart->isEmpty());
    }

    public function test_clear_empties_the_cart(): void
    {
        $this->cart->add($this->makeProduct(), 1);
        $this->cart->add($this->makeProduct(['nombre' => 'Otro']), 1);

        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty());
        $this->assertSame(0, $this->cart->count());
    }

    public function test_count_sums_quantities_across_all_lines(): void
    {
        $this->cart->add($this->makeProduct(['stock_actual' => 10]), 2);
        $this->cart->add($this->makeProduct(['nombre' => 'Otro', 'stock_actual' => 10]), 3);

        $this->assertSame(5, $this->cart->count());
    }

    public function test_total_uses_the_price_frozen_at_add_time_not_the_current_price(): void
    {
        $product = $this->makeProduct(['precio_venta' => 100]);
        $this->cart->add($product, 2);

        // El producto sube de precio DESPUES de agregarlo al carrito.
        $product->update(['precio_venta' => 500]);

        $this->assertSame(200.0, $this->cart->total());
    }

    public function test_is_empty_reflects_cart_state(): void
    {
        $this->assertTrue($this->cart->isEmpty());

        $this->cart->add($this->makeProduct(), 1);

        $this->assertFalse($this->cart->isEmpty());
    }
}
