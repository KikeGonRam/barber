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
     * Lanza InsufficientStockException si una salida deja el stock en negativo.
     *
     * IMPORTANTE (Fase 4, auditoria): ->lockForUpdate() NO es una garantia real
     * aqui -- confirmado leyendo mongodb/laravel-mongodb: ni Query\Builder ni
     * Eloquent\Builder lo sobreescriben, asi que hereda el lockForUpdate() base
     * de Illuminate (que solo marca una bandera para el grammar SQL "FOR
     * UPDATE"). El grammar de Mongo no traduce eso a nada -- es un no-op
     * silencioso, no un error, lo que lo hacia parecer una proteccion real sin
     * serlo. La proteccion real es el decrement condicional de abajo: un
     * unico op atomico de Mongo ($inc con filtro stock_actual >= cantidad),
     * que Mongo evalua y aplica sobre el valor actual del documento en una
     * sola operacion -- no se puede colar una lectura obsoleta entre el
     * chequeo y la escritura como con lockForUpdate()+decrement() por
     * separado, sin importar cuantas escrituras concurrentes lleguen.
     */
    public function registerMovement(array $payload, string $userId)
    {
        // El driver mongodb/laravel-mongodb no soporta transacciones anidadas
        // (no hay savepoints -- Session::startTransaction() truena con
        // "Transaction already in progress" si ya hay una activa en la misma
        // sesion). Cuando algo como OrderService::place() ya nos llama desde
        // dentro de su propia DB::transaction(), debemos participar en esa
        // transaccion existente en vez de abrir una nueva; solo abrimos una
        // propia cuando nos llaman de forma standalone (p.ej. InventoryController).
        if (DB::transactionLevel() > 0) {
            return $this->doRegisterMovement($payload, $userId);
        }

        return DB::transaction(fn () => $this->doRegisterMovement($payload, $userId));
    }

    private function doRegisterMovement(array $payload, string $userId)
    {
        $quantity = (int) $payload['cantidad'];
        $type = (string) $payload['tipo'];
        $productId = (string) $payload['product_id'];

        if ($type === 'entrada') {
            $product = Product::query()->findOrFail($productId);
            $product->increment('stock_actual', $quantity);

            // El pedido llegó: ya no hace falta silenciar la alerta a mano.
            if ($product->reabastecimiento_pedido_en) {
                $product->update(['reabastecimiento_pedido_en' => null, 'reabastecimiento_pedido_por' => null]);
            }
        } else {
            $affected = Product::query()
                ->where('_id', $productId)
                ->where('stock_actual', '>=', $quantity)
                ->decrement('stock_actual', $quantity);

            if ($affected === 0) {
                // Puede ser porque el producto no existe (mismo mensaje que
                // findOrFail daria) o porque el stock ya no alcanza -- en
                // ambos casos el resultado correcto para quien llama es el
                // mismo error de stock insuficiente, sin una segunda
                // lectura que reintroduciria la misma condicion de carrera.
                throw new InsufficientStockException('No hay stock suficiente para registrar la salida.');
            }
        }

        return $this->movements->create([
            'product_id' => $productId,
            'tipo' => $type,
            'cantidad' => $quantity,
            'motivo' => $payload['motivo'] ?? null,
            'appointment_id' => $payload['appointment_id'] ?? null,
            'user_id' => $userId,
            'fecha' => $payload['fecha'] ?? now(),
        ]);
    }
}
