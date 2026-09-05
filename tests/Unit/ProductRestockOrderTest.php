<?php

namespace Tests\Unit;

use App\Models\Product;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ProductRestockOrderTest extends TestCase
{
    private function productOrderedDaysAgo(?int $days): Product
    {
        $product = new Product;

        if ($days !== null) {
            $product->reabastecimiento_pedido_en = Carbon::now()->subDays($days);
        }

        return $product;
    }

    public function test_has_no_pending_order_when_never_marked(): void
    {
        $product = $this->productOrderedDaysAgo(null);

        $this->assertFalse($product->hasPendingRestockOrder());
    }

    public function test_has_pending_order_within_the_grace_window(): void
    {
        $product = $this->productOrderedDaysAgo(3);

        $this->assertTrue($product->hasPendingRestockOrder());
    }

    public function test_pending_order_expires_after_the_grace_window(): void
    {
        // Justo en el límite (7 días) ya no cuenta como pendiente: la alerta
        // debe reaparecer si el pedido nunca llegó.
        $exactBoundary = $this->productOrderedDaysAgo(Product::RESTOCK_GRACE_DAYS);
        $pastBoundary = $this->productOrderedDaysAgo(Product::RESTOCK_GRACE_DAYS + 1);

        $this->assertFalse($exactBoundary->hasPendingRestockOrder());
        $this->assertFalse($pastBoundary->hasPendingRestockOrder());
    }
}
