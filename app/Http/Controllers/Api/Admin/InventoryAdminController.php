<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\InventoryMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAdminController
{
    public function getProducts(Request $request): JsonResponse
    {
        $search   = $request->query('search', '');
        $category = $request->query('category');
        $status   = $request->query('status');

        $query = Product::query();

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        if ($category) {
            $query->where('categoria', $category);
        }

        $products = $query->get()->map(fn ($p) => $this->productPayload($p));

        if ($status) {
            $products = $products->filter(fn ($p) => $p['status'] === $status)->values();
        }

        return response()->json([
            'success' => true,
            'data'    => $products,
            'total'   => $products->count(),
        ]);
    }

    public function show($productId): JsonResponse
    {
        $product   = Product::findOrFail($productId);
        $movements = InventoryMovement::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->productPayload($product), [
                'monthlyConsumption' => $this->getMonthlyConsumption($productId),
                'daysUntilStockOut'  => $this->calculateDaysUntilStockOut($product),
                'movements'          => $movements->map(fn ($m) => [
                    'id'       => $m->id,
                    'tipo'     => $m->tipo,
                    'cantidad' => $m->cantidad,
                    'motivo'   => $m->motivo,
                    'fecha'    => optional($m->fecha)->toIso8601String() ?? optional($m->created_at)->toIso8601String(),
                ]),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'       => 'required|string|max:255',
            'categoria'    => 'required|string',
            'stock_actual' => 'required|integer|min:0',
            'stock_minimo' => 'required|integer|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'precio_compra'=> 'nullable|numeric|min:0',
            'descripcion'  => 'nullable|string',
            'tipo'         => 'nullable|string',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente',
            'data'    => $product,
        ], 201);
    }

    public function update($productId, Request $request): JsonResponse
    {
        $product   = Product::findOrFail($productId);
        $validated = $request->validate([
            'nombre'       => 'string|max:255',
            'categoria'    => 'string',
            'stock_actual' => 'integer|min:0',
            'stock_minimo' => 'integer|min:0',
            'precio_venta' => 'numeric|min:0',
            'precio_compra'=> 'nullable|numeric|min:0',
            'descripcion'  => 'nullable|string',
            'tipo'         => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente',
            'data'    => $product,
        ]);
    }

    public function destroy($productId): JsonResponse
    {
        Product::findOrFail($productId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente',
        ]);
    }

    public function recordMovement($productId, Request $request): JsonResponse
    {
        $product   = Product::findOrFail($productId);
        $validated = $request->validate([
            'tipo'    => 'required|in:entrada,salida',
            'cantidad'=> 'required|integer|min:1',
            'motivo'  => 'required|string',
        ]);

        if ($validated['tipo'] === 'entrada') {
            $product->increment('stock_actual', $validated['cantidad']);
        } else {
            $product->decrement('stock_actual', $validated['cantidad']);
        }

        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'tipo'       => $validated['tipo'],
            'cantidad'   => $validated['cantidad'],
            'motivo'     => $validated['motivo'],
            'user_id'    => (string) auth()->id(),
            'fecha'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado correctamente',
            'data'    => [
                'movement'    => $movement,
                'stock_actual'=> $product->fresh()->stock_actual,
            ],
        ]);
    }

    public function getMovements(Request $request): JsonResponse
    {
        $startDate = $request->query('startDate', Carbon::now()->subMonth()->toDateTimeString());
        $endDate   = $request->query('endDate', Carbon::now()->toDateTimeString());

        $movements = InventoryMovement::whereBetween('created_at', [$startDate, $endDate])
            ->with('product:id,nombre')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => [
                'id'      => $m->id,
                'product' => $m->product?->nombre,
                'tipo'    => $m->tipo,
                'cantidad'=> $m->cantidad,
                'motivo'  => $m->motivo,
                'fecha'   => optional($m->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $movements,
            'total'   => $movements->count(),
        ]);
    }

    public function getSummary(): JsonResponse
    {
        $products = Product::all(['nombre', 'categoria', 'stock_actual', 'stock_minimo', 'precio_venta', 'precio_compra']);

        return response()->json([
            'success' => true,
            'data'    => [
                'totalProducts' => $products->count(),
                'totalValue'    => $products->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                'lowStockCount' => $products->filter(fn ($p) => ($p->stock_actual ?? 0) <= ($p->stock_minimo ?? 0))->count(),
                'criticalCount' => $products->filter(fn ($p) => ($p->stock_actual ?? 0) < (($p->stock_minimo ?? 0) / 2))->count(),
                'categories'    => $products->pluck('categoria')->unique()->values(),
                'byCategory'    => $products->groupBy('categoria')->map(fn ($g) => [
                    'count'      => $g->count(),
                    'totalValue' => $g->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                ]),
            ],
        ]);
    }

    public function getLowStockProducts(): JsonResponse
    {
        $products = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'nombre'           => $p->nombre,
                'categoria'        => $p->categoria,
                'stock_actual'     => $p->stock_actual,
                'stock_minimo'     => $p->stock_minimo,
                'status'           => $this->getStockStatus($p),
                'daysUntilStockOut'=> $this->calculateDaysUntilStockOut($p),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $products,
            'count'   => $products->count(),
        ]);
    }

    private function productPayload(Product $product): array
    {
        return [
            'id'          => $product->id,
            'nombre'      => $product->nombre,
            'categoria'   => $product->categoria,
            'stock_actual'=> $product->stock_actual,
            'stock_minimo'=> $product->stock_minimo,
            'precio_venta'=> $product->precio_venta,
            'precio_compra'=> $product->precio_compra,
            'tipo'        => $product->tipo,
            'status'      => $this->getStockStatus($product),
            'totalValue'  => (float) ($product->stock_actual ?? 0) * (float) ($product->precio_venta ?? 0),
            'updatedAt'   => optional($product->updated_at)->toIso8601String(),
        ];
    }

    private function getStockStatus(Product $product): string
    {
        $stock = (int) ($product->stock_actual ?? 0);
        $min   = (int) ($product->stock_minimo ?? 0);

        if ($stock === 0) return 'empty';
        if ($stock < ($min / 2)) return 'critical';
        if ($stock <= $min) return 'low';
        return 'ok';
    }

    private function getMonthlyConsumption(string $productId): int
    {
        return (int) InventoryMovement::where('product_id', $productId)
            ->where('tipo', 'salida')
            ->where('created_at', '>=', Carbon::now()->subMonth())
            ->sum('cantidad');
    }

    private function calculateDaysUntilStockOut(Product $product): ?int
    {
        $monthlyConsumption = $this->getMonthlyConsumption((string) $product->id);
        if ($monthlyConsumption === 0) return null;

        $dailyConsumption = $monthlyConsumption / 30;
        return (int) (((int) ($product->stock_actual ?? 0)) / max($dailyConsumption, 0.1));
    }
}
