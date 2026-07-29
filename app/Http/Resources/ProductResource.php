<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma un producto de inventario a la estructura consumida por la app móvil.
 * Reemplaza el antiguo método privado productPayload().
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria,
            'descripcion' => $this->descripcion,
            'stock_actual' => $this->stock_actual,
            'stock_minimo' => $this->stock_minimo,
            'precio_venta' => $this->precio_venta,
            'precio_compra' => $this->precio_compra,
            'tipo' => $this->tipo,
            'activo' => $this->isActive(),
            'active' => $this->isActive(),
            'status' => $this->stockStatus(),
            'totalValue' => (float) ($this->stock_actual ?? 0) * (float) ($this->precio_venta ?? 0),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Estado del stock — misma lógica que el antiguo getStockStatus().
     */
    private function stockStatus(): string
    {
        $stock = (int) ($this->stock_actual ?? 0);
        $min = (int) ($this->stock_minimo ?? 0);

        if ($stock === 0) {
            return 'empty';
        }
        if ($stock < ($min / 2)) {
            return 'critical';
        }
        if ($stock <= $min) {
            return 'low';
        }

        return 'ok';
    }
}
