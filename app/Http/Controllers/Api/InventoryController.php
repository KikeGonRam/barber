<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    public function products(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $filters = $request->only(['categoria', 'tipo']);
        $products = $this->inventoryService->listProducts($filters, 50);

        return response()->json([
            'data' => collect($products->items())->map(fn ($product) => [
                'id' => $product->id,
                'nombre' => $product->nombre,
                'categoria' => $product->categoria,
                'descripcion' => $product->descripcion,
                'tipo' => $product->tipo,
                'stock_actual' => $product->stock_actual,
                'stock_minimo' => $product->stock_minimo,
                'precio_compra' => $product->precio_compra,
                'precio_venta' => $product->precio_venta,
                'active' => (bool) $product->active,
                'low_stock' => (int) $product->stock_actual <= (int) $product->stock_minimo,
                'imagen_url' => $product->imagen ? Storage::disk('public')->url($product->imagen) : null,
            ])->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $filters = $request->only(['tipo', 'product_id']);
        $movements = $this->inventoryService->listMovements($filters, 50);

        return response()->json([
            'data' => collect($movements->items())->map(fn ($movement) => [
                'id' => $movement->id,
                'tipo' => $movement->tipo,
                'cantidad' => $movement->cantidad,
                'motivo' => $movement->motivo,
                'fecha' => optional($movement->fecha)->toIso8601String(),
                'product' => [
                    'id' => $movement->product?->id,
                    'nombre' => $movement->product?->nombre,
                ],
                'user' => [
                    'id' => $movement->user?->id,
                    'name' => $movement->user?->name,
                ],
                'appointment' => [
                    'id' => $movement->appointment?->id,
                    'fecha' => optional($movement->appointment?->fecha)->toDateString(),
                    'client' => $movement->appointment?->client?->user?->name,
                ],
            ])->values(),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }
}