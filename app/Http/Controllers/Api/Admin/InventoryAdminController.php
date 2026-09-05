<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\ProductResource;
use App\Models\InventoryMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador de administración de inventario (panel admin).
 * Expone CRUD de productos, registro de movimientos de stock (entradas/salidas),
 * resumen agregado y alertas de stock bajo/crítico.
 */
class InventoryAdminController
{
    // Defensa en profundidad: aunque la ruta ya exige role.custom:administrador,
    // este guard evita que un descuido en routes/api.php exponga/mute el inventario.
    private function authorizeAdmin(): void
    {
        abort_if(! request()->user()?->hasRole('administrador'), 403, 'Solo administradores pueden acceder a este recurso.');
    }

    // Lista productos con filtros de búsqueda/categoría/status (status se filtra en PHP porque depende del ProductResource)
    public function getProducts(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $search = $request->query('search', '');
        $category = $request->query('category');
        $status = $request->query('status');

        $query = Product::query();

        if ($search) {
            $query->where('nombre', 'like', "%{$search}%");
        }

        if ($category) {
            $query->where('categoria', $category);
        }

        $products = $query->get()->map(fn ($p) => (new ProductResource($p))->resolve());

        if ($status) {
            $products = $products->filter(fn ($p) => $p['status'] === $status)->values();
        }

        return response()->json([
            'success' => true,
            'data' => $products,
            'total' => $products->count(),
        ]);
    }

    // Detalle de un producto: incluye consumo mensual, estimación de días hasta agotarse y últimos 20 movimientos
    public function show($productId): JsonResponse
    {
        $this->authorizeAdmin();
        $product = Product::findOrFail($productId);
        $movements = InventoryMovement::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => array_merge((new ProductResource($product))->resolve(), [
                'monthlyConsumption' => $this->getMonthlyConsumption($productId),
                'daysUntilStockOut' => $this->calculateDaysUntilStockOut($product),
                'movements' => $movements->map(fn ($m) => [
                    'id' => $m->id,
                    'tipo' => $m->tipo,
                    'cantidad' => $m->cantidad,
                    'motivo' => $m->motivo,
                    'fecha' => optional($m->fecha)->toIso8601String() ?? optional($m->created_at)->toIso8601String(),
                ]),
            ]),
        ]);
    }

    // Crea un producto nuevo (normalizePayload homogeniza los alias activo/active y demás campos)
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria' => 'required|string',
            'stock_actual' => 'required|integer|min:0',
            'stock_minimo' => 'required|integer|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'precio_compra' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string',
            'tipo' => ['nullable', 'string', Rule::in([...Product::SALE_TYPES, ...Product::SUPPLY_TYPES])],
            'activo' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $product = Product::create(Product::normalizePayload($validated));

        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente',
            'data' => $product,
        ], 201);
    }

    // Actualiza un producto existente (todos los campos son opcionales para permitir updates parciales)
    public function update($productId, Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $product = Product::findOrFail($productId);
        $validated = $request->validate([
            'nombre' => 'string|max:255',
            'categoria' => 'string',
            'stock_actual' => 'integer|min:0',
            'stock_minimo' => 'integer|min:0',
            'precio_venta' => 'numeric|min:0',
            'precio_compra' => 'nullable|numeric|min:0',
            'descripcion' => 'nullable|string',
            'tipo' => ['nullable', 'string', Rule::in([...Product::SALE_TYPES, ...Product::SUPPLY_TYPES])],
            'activo' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $product->update(Product::normalizePayload($validated));

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente',
            'data' => $product,
        ]);
    }

    // Elimina un producto por id
    public function destroy($productId): JsonResponse
    {
        $this->authorizeAdmin();
        Product::findOrFail($productId)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente',
        ]);
    }

    // Registra un movimiento de stock (entrada/salida) y ajusta el stock_actual del producto en consecuencia
    public function recordMovement($productId, Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $product = Product::findOrFail($productId);
        $validated = $request->validate([
            'tipo' => 'required|in:entrada,salida',
            'cantidad' => 'required|integer|min:1',
            'motivo' => 'required|string',
        ]);

        if ($validated['tipo'] === 'entrada') {
            $product->increment('stock_actual', $validated['cantidad']);

            // El pedido llegó: ya no hace falta silenciar la alerta a mano.
            if ($product->reabastecimiento_pedido_en) {
                $product->update(['reabastecimiento_pedido_en' => null, 'reabastecimiento_pedido_por' => null]);
            }
        } else {
            if ((int) $product->stock_actual < (int) $validated['cantidad']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay stock suficiente para registrar la salida.',
                ], 422);
            }

            $product->decrement('stock_actual', $validated['cantidad']);
        }

        $product->refresh();

        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'tipo' => $validated['tipo'],
            'cantidad' => $validated['cantidad'],
            'motivo' => $validated['motivo'],
            'user_id' => (string) auth()->id(),
            'fecha' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado correctamente',
            'data' => [
                'movement' => $movement,
                'stock_actual' => $product->stock_actual,
            ],
        ]);
    }

    // Lista movimientos de stock dentro de un rango de fechas (por defecto, el último mes)
    public function getMovements(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $startDate = $request->query('startDate', Carbon::now()->subMonth()->toDateTimeString());
        $endDate = $request->query('endDate', Carbon::now()->toDateTimeString());

        $movements = InventoryMovement::whereBetween('created_at', [$startDate, $endDate])
            ->with('product:id,nombre')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'product' => $m->product?->nombre,
                'tipo' => $m->tipo,
                'cantidad' => $m->cantidad,
                'motivo' => $m->motivo,
                'fecha' => optional($m->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $movements,
            'total' => $movements->count(),
        ]);
    }

    // Resumen agregado del inventario: valor total, conteos de stock bajo/crítico y desglose por categoría
    public function getSummary(): JsonResponse
    {
        $this->authorizeAdmin();
        $products = Product::all(['nombre', 'categoria', 'stock_actual', 'stock_minimo', 'precio_venta', 'precio_compra']);

        return response()->json([
            'success' => true,
            'data' => [
                'totalProducts' => $products->count(),
                'totalValue' => $products->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                'lowStockCount' => $products->filter(fn ($p) => ($p->stock_actual ?? 0) <= ($p->stock_minimo ?? 0))->count(),
                'criticalCount' => $products->filter(fn ($p) => ($p->stock_actual ?? 0) < (($p->stock_minimo ?? 0) / 2))->count(),
                'categories' => $products->pluck('categoria')->unique()->values(),
                'byCategory' => $products->groupBy('categoria')->map(fn ($g) => [
                    'count' => $g->count(),
                    'totalValue' => $g->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                ]),
            ],
        ]);
    }

    // Lista productos con stock en o por debajo del mínimo, con estimación de días hasta agotarse
    public function getLowStockProducts(): JsonResponse
    {
        $this->authorizeAdmin();
        // Comparación entre dos campos del mismo documento: requiere $expr, no un where() normal
        $products = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->get();
        $productIds = $products->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query for monthly consumption (was N queries via calculateDaysUntilStockOut)
        $monthlyConsumption = InventoryMovement::where('tipo', 'salida')
            ->where('created_at', '>=', Carbon::now()->subMonth())
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'cantidad'])
            ->groupBy(fn ($m) => (string) $m->product_id)
            ->map(fn ($g) => (int) $g->sum('cantidad'));

        $data = $products->map(function ($p) use ($monthlyConsumption) {
            $consumption = $monthlyConsumption->get((string) $p->id, 0);
            $dailyRate = $consumption > 0 ? $consumption / 30 : 0;
            $daysUntilOut = ($dailyRate > 0)
                ? (int) (((int) ($p->stock_actual ?? 0)) / max($dailyRate, 0.1))
                : null;

            return [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'categoria' => $p->categoria,
                'stock_actual' => $p->stock_actual,
                'stock_minimo' => $p->stock_minimo,
                'status' => $this->getStockStatus($p),
                'daysUntilStockOut' => $daysUntilOut,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    // Clasifica el estado de stock de un producto: empty/critical/low/ok
    private function getStockStatus(Product $product): string
    {
        $stock = (int) ($product->stock_actual ?? 0);
        $min = (int) ($product->stock_minimo ?? 0);

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

    // Suma las salidas de stock del producto en el último mes
    private function getMonthlyConsumption(string $productId): int
    {
        return (int) InventoryMovement::where('product_id', $productId)
            ->where('tipo', 'salida')
            ->where('created_at', '>=', Carbon::now()->subMonth())
            ->sum('cantidad');
    }

    // Estima días restantes de stock según el consumo promedio diario del último mes (null si no hay consumo)
    private function calculateDaysUntilStockOut(Product $product): ?int
    {
        $monthlyConsumption = $this->getMonthlyConsumption((string) $product->id);
        if ($monthlyConsumption === 0) {
            return null;
        }

        $dailyConsumption = $monthlyConsumption / 30;

        return (int) (((int) ($product->stock_actual ?? 0)) / max($dailyConsumption, 0.1));
    }
}
