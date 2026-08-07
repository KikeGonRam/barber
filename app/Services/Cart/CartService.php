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
     * Devuelve las lineas actuales del carrito, indexadas por product_id.
     * @return array<string, array{product_id:string,nombre:string,precio:float,imagen:?string,cantidad:int}>
     */
    public function items(): array
    {
        return Session::get(self::KEY, []);
    }

    /**
     * Agrega (o incrementa) un producto en el carrito. Efecto secundario:
     * persiste el carrito completo en sesion. Congela nombre/precio/imagen
     * al momento de agregar, asi el carrito no cambia si el producto se
     * edita despues (el precio final real se recalcula en checkout).
     */
    public function add(Product $product, int $qty = 1): void
    {
        $qty = max(1, $qty);
        $items = $this->items();
        $id = (string) $product->id;

        $current = $items[$id]['cantidad'] ?? 0;
        // No permitir exceder el stock disponible.
        $max = max(0, (int) $product->stock_actual);
        // Si no hay stock configurado (max=0), no se limita para no bloquear
        // productos sin control de inventario; si hay stock, se topa ahi.
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

    /**
     * Cambia la cantidad de una linea existente; si la cantidad es <= 0,
     * elimina la linea en vez de dejarla en cero. Persiste en sesion.
     */
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

    /**
     * Quita una linea del carrito por completo. Persiste en sesion.
     */
    public function remove(string $productId): void
    {
        $items = $this->items();
        unset($items[$productId]);
        $this->save($items);
    }

    /**
     * Vacia el carrito (ej. tras completar el checkout).
     */
    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * Cantidad total de unidades en el carrito (suma de todas las lineas).
     */
    public function count(): int
    {
        return array_sum(array_column($this->items(), 'cantidad'));
    }

    /**
     * Total en dinero del carrito, usando el precio congelado al agregar
     * cada linea (no el precio actual del producto).
     */
    public function total(): float
    {
        return array_reduce($this->items(), fn ($carry, $i) => $carry + ($i['precio'] * $i['cantidad']), 0.0);
    }

    public function isEmpty(): bool
    {
        return empty($this->items());
    }

    /**
     * Escribe el estado completo del carrito en la sesion (reemplaza).
     */
    private function save(array $items): void
    {
        Session::put(self::KEY, $items);
    }
}
