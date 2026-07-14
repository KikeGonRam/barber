<?php

namespace App\Services\Cart;

use App\Models\Product;
use Illuminate\Support\Facades\Session;

/**
 * Carrito de compras en sesion. Guarda lineas por producto con cantidad.
 */
class CartService
{
    private const KEY = 'cart';

    /**
     * @return array<string, array{product_id:string,nombre:string,precio:float,imagen:?string,cantidad:int}>
     */
    public function items(): array
    {
        return Session::get(self::KEY, []);
    }

    public function add(Product $product, int $qty = 1): void
    {
        $qty = max(1, $qty);
        $items = $this->items();
        $id = (string) $product->id;

        $current = $items[$id]['cantidad'] ?? 0;
        // No permitir exceder el stock disponible.
        $max = max(0, (int) $product->stock_actual);
        $nueva = min($current + $qty, $max > 0 ? $max : $current + $qty);

        $items[$id] = [
            'product_id' => $id,
            'nombre' => (string) $product->nombre,
            'precio' => (float) $product->precio_venta,
            'imagen' => $product->imagen,
            'cantidad' => max(1, $nueva),
        ];

        $this->save($items);
    }

    public function update(string $productId, int $qty): void
    {
        $items = $this->items();
        if (! isset($items[$productId])) {
            return;
        }

        if ($qty <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId]['cantidad'] = $qty;
        }

        $this->save($items);
    }

    public function remove(string $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);
        $this->save($items);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum(array_column($this->items(), 'cantidad'));
    }

    public function total(): float
    {
        return array_reduce($this->items(), fn ($carry, $i) => $carry + ($i['precio'] * $i['cantidad']), 0.0);
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }

    private function save(array $items): void
    {
        Session::put(self::KEY, $items);
    }
}
