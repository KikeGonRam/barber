<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class InventoryAdminController extends Controller
{
    /**
     * Obtener lista de productos con estado de stock
     */
    public function getProducts(Request $request)
    {
        $search = $request->query('search', '');
        $category = $request->query('category', null);
        $status = $request->query('status', null);

        $query = Product::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category) {
            $query->where('category', $category);
        }

        $products = $query->get()->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'quantity' => $product->quantity,
                'minStock' => $product->min_stock,
                'price' => $product->price,
                'supplier' => $product->supplier,
                'totalValue' => $product->quantity * $product->price,
                'status' => $this->getStockStatus($product),
                'updatedAt' => $product->updated_at,
            ];
        });

        if ($status) {
            $products = $products->filter(function ($product) use ($status) {
                return $product['status'] === $status;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $products,
            'total' => $products->count(),
        ]);
    }

    /**
     * Obtener detalles de un producto
     */
    public function show($productId)
    {
        $product = Product::findOrFail($productId);

        $movements = InventoryMovement::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'quantity' => $product->quantity,
                'minStock' => $product->min_stock,
                'price' => $product->price,
                'cost' => $product->cost,
                'supplier' => $product->supplier,
                'supplierContact' => $product->supplier_contact,
                'status' => $this->getStockStatus($product),
                'totalValue' => $product->quantity * $product->price,
                'monthlyConsumption' => $this->getMonthlyConsumption($productId),
                'daysUntilStockOut' => $this->calculateDaysUntilStockOut($product),
                'movements' => $movements->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'type' => $m->type,
                        'quantity' => $m->quantity,
                        'reason' => $m->reason,
                        'date' => $m->created_at,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Crear nuevo producto
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'quantity' => 'required|integer|min:0',
            'minStock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string',
            'supplierContact' => 'nullable|string',
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'quantity' => $validated['quantity'],
            'min_stock' => $validated['minStock'],
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0,
            'supplier' => $validated['supplier'],
            'supplier_contact' => $validated['supplierContact'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Producto creado correctamente',
            'data' => $product,
        ], 201);
    }

    /**
     * Actualizar producto
     */
    public function update($productId, Request $request)
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'category' => 'string',
            'quantity' => 'integer|min:0',
            'minStock' => 'integer|min:0',
            'price' => 'numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'supplier' => 'nullable|string',
            'supplierContact' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente',
            'data' => $product,
        ]);
    }

    /**
     * Eliminar producto
     */
    public function destroy($productId)
    {
        $product = Product::findOrFail($productId);
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado correctamente',
        ]);
    }

    /**
     * Registrar movimiento de inventario
     */
    public function recordMovement($productId, Request $request)
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'type' => 'required|in:entrada,salida,ajuste',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        // Actualizar cantidad del producto
        if ($validated['type'] === 'entrada' || $validated['type'] === 'ajuste') {
            $product->quantity += $validated['quantity'];
        } else {
            $product->quantity = max(0, $product->quantity - $validated['quantity']);
        }

        $product->save();

        // Registrar movimiento
        $movement = InventoryMovement::create([
            'product_id' => $productId,
            'type' => $validated['type'],
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'],
            'reference' => $validated['reference'],
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado correctamente',
            'data' => [
                'movement' => $movement,
                'newQuantity' => $product->quantity,
            ],
        ]);
    }

    /**
     * Obtener movimientos de inventario
     */
    public function getMovements(Request $request)
    {
        $startDate = $request->query('startDate', Carbon::now()->subMonth());
        $endDate = $request->query('endDate', Carbon::now());

        $movements = InventoryMovement::whereBetween('created_at', [$startDate, $endDate])
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'product' => $movement->product?->name,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'reason' => $movement->reason,
                    'date' => $movement->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $movements,
            'total' => $movements->count(),
        ]);
    }

    /**
     * Obtener resumen de inventario
     */
    public function getSummary()
    {
        $products = Product::all();

        return response()->json([
            'success' => true,
            'data' => [
                'totalProducts' => $products->count(),
                'totalValue' => $products->sum(function ($p) {
                    return $p->quantity * $p->price;
                }),
                'lowStockCount' => $products->filter(function ($p) {
                    return $p->quantity <= $p->min_stock;
                })->count(),
                'criticalCount' => $products->filter(function ($p) {
                    return $p->quantity < ($p->min_stock / 2);
                })->count(),
                'categories' => $products->groupBy('category')->keys(),
                'byCategory' => $products->groupBy('category')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'totalValue' => $group->sum(function ($p) {
                            return $p->quantity * $p->price;
                        }),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Obtener productos con stock bajo
     */
    public function getLowStockProducts()
    {
        $products = Product::whereRaw('quantity <= min_stock')
            ->orWhereRaw('quantity < (min_stock / 2)')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'quantity' => $product->quantity,
                    'minStock' => $product->min_stock,
                    'status' => $this->getStockStatus($product),
                    'daysUntilStockOut' => $this->calculateDaysUntilStockOut($product),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => $products->count(),
        ]);
    }

    /**
     * Helper: Obtener estado del stock
     */
    private function getStockStatus($product)
    {
        if ($product->quantity === 0) return 'empty';
        if ($product->quantity < $product->min_stock / 2) return 'critical';
        if ($product->quantity <= $product->min_stock) return 'low';
        return 'ok';
    }

    /**
     * Helper: Calcular consumo mensual promedio
     */
    private function getMonthlyConsumption($productId)
    {
        $movements = InventoryMovement::where('product_id', $productId)
            ->where('type', 'salida')
            ->where('created_at', '>=', Carbon::now()->subMonth())
            ->sum('quantity');

        return $movements;
    }

    /**
     * Helper: Calcular días hasta agotar stock
     */
    private function calculateDaysUntilStockOut($product)
    {
        $monthlyConsumption = $this->getMonthlyConsumption($product->id);

        if ($monthlyConsumption === 0) return null;

        $daysPerMonth = 30;
        $dailyConsumption = $monthlyConsumption / $daysPerMonth;

        return intval($product->quantity / max($dailyConsumption, 0.1));
    }
}
