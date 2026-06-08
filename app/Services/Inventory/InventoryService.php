<?php

namespace App\Services\Inventory;

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Product;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly InventoryMovementRepositoryInterface $movements,
    ) {}

    public function listProducts(array $filters = [], int $perPage = 15)
    {
        return $this->products->paginateWithFilters($filters, $perPage);
    }

    public function listMovements(array $filters = [], int $perPage = 15)
    {
        return $this->movements->paginateWithFilters($filters, $perPage);
    }

    public function lowStockCount(): int
    {
        return $this->products->lowStockCount();
    }

    public function createProduct(array $payload)
    {
        return $this->products->create($payload);
    }

    public function updateProduct(Product $product, array $payload): bool
    {
        return $this->products->update($product->id, $payload);
    }

    public function deleteProduct(Product $product): bool
    {
        return $this->products->delete($product->id);
    }

    public function registerMovement(array $payload, int $userId)
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
