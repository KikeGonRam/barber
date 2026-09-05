<?php

namespace App\Services\Inventory;

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Product;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Orquesta el inventario de productos (tienda) y sus movimientos de stock
 * (entradas/salidas). Valida stock disponible y mantiene stock_actual del
 * producto en sincronía con el historial de movimientos.
 */
class InventoryService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly InventoryMovementRepositoryInterface $movements,
    ) {}

    /**
     * Lista paginada de productos con filtros.
     */
    public function listProducts(array $filters = [], int $perPage = 15)
    {
        return $this->products->paginateWithFilters($filters, $perPage);
    }

    /**
     * Lista paginada de movimientos de inventario con filtros.
     */
    public function listMovements(array $filters = [], int $perPage = 15)
    {
        return $this->movements->paginateWithFilters($filters, $perPage);
    }

    /**
     * Cantidad de productos con stock_actual por debajo (o igual) del stock mínimo.
     */
    public function lowStockCount(): int
    {
        return $this->products->lowStockCount();
    }

    /**
     * Crea un producto nuevo. Normaliza el payload antes de persistir (tipos/campos consistentes).
     */
    public function createProduct(array $payload)
    {
        return $this->products->create(Product::normalizePayload($payload));
    }

    /**
     * Actualiza un producto existente. Normaliza el payload antes de persistir.
     */
    public function updateProduct(Product $product, array $payload): bool
    {
        return $this->products->update($product->id, Product::normalizePayload($payload));
    }

    /**
     * Marca un producto de stock bajo como "ya pedido" para silenciar la alerta
     * diaria de inventory:low-stock-alert durante Product::RESTOCK_GRACE_DAYS.
     * Se limpia automáticamente en registerMovement() al registrar una entrada real
     * de stock, sin necesidad de que nadie la desmarque a mano.
     */
    public function markProductOrdered(Product $product, string $userId): bool
    {
        return $this->products->update($product->id, [
            'reabastecimiento_pedido_en' => now(),
            'reabastecimiento_pedido_por' => $userId,
        ]);
    }

    /**
     * Elimina un producto.
     */
    public function deleteProduct(Product $product): bool
    {
        if (! empty($product->imagen) && Storage::disk('public')->exists($product->imagen)) {
            Storage::disk('public')->delete($product->imagen);
        }

        return $this->products->delete($product->id);
    }

    /**
     * Registra un movimiento de stock (entrada/salida) y actualiza stock_actual del producto.
     * Efecto secundario: transacción DB con lockForUpdate para evitar condiciones de carrera
     * si dos movimientos del mismo producto se registran en paralelo. Lanza
     * InsufficientStockException si una salida deja el stock en negativo.
     */
    public function registerMovement(array $payload, string $userId)
    {
        return DB::transaction(function () use ($payload, $userId) {
            /** @var Product $product */
            $product = Product::query()->lockForUpdate()->findOrFail($payload['product_id']);

            $quantity = (int) $payload['cantidad'];
            $type = (string) $payload['tipo'];

            if ($type === 'salida' && $product->stock_actual < $quantity) {
                throw new InsufficientStockException('No hay stock suficiente para registrar la salida.');
            }

            if ($type === 'entrada') {
                $product->increment('stock_actual', $quantity);

                // El pedido llegó: ya no hace falta silenciar la alerta a mano.
                if ($product->reabastecimiento_pedido_en) {
                    $product->update(['reabastecimiento_pedido_en' => null, 'reabastecimiento_pedido_por' => null]);
                }
            } else {
                $product->decrement('stock_actual', $quantity);
            }

            return $this->movements->create([
                'product_id' => $product->id,
                'tipo' => $type,
                'cantidad' => $quantity,
                'motivo' => $payload['motivo'] ?? null,
                'appointment_id' => $payload['appointment_id'] ?? null,
                'user_id' => $userId,
                'fecha' => $payload['fecha'] ?? now(),
            ]);
        });
    }
}
