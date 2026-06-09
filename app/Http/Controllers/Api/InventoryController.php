<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group Inventario
 *
 * Gestión de productos, stock y movimientos de almacén.
 */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Listar Productos
     *
     * Obtiene el catálogo de productos con su stock actual y alertas de stock bajo.
     *
     * @authenticated
     *
     * @queryParam categoria string Filtrar por categoría. Example: Barbería
     * @queryParam tipo string Filtrar por tipo (insumo, venta). Example: venta
     *
     * @response {
     *  "data": [
     *    {
     *      "id": 1,
     *      "nombre": "Pomada Mate",
     *      "categoria": "Fijación",
     *      "stock_actual": 15,
     *      "low_stock": false,
     *      "precio_venta": 25.00
     *    }
     *  ]
     * }
     */
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

    /**
     * Historial de Movimientos
     *
     * Obtiene el registro de entradas y salidas de productos del almacén.
     *
     * @authenticated
     *
     * @response {
     *  "data": [
     *    {
     *      "id": 10,
     *      "producto": "Shampoo Anti-caspa",
     *      "tipo": "entrada",
     *      "cantidad": 12,
     *      "motivo": "Reposición de stock",
     *      "responsable": "Admin",
     *      "fecha": "2026-04-10T10:30:00Z"
     *    }
     *  ]
     * }
     */
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

    public function storeProduct(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'stock_actual' => ['required', 'integer', 'min:0'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'tipo' => ['required', 'in:venta_cliente,insumo_trabajo'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('products', 'public');
        }

        $created = $this->inventoryService->createProduct($data);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => [
                'id' => $created->id,
                'nombre' => $created->nombre,
                'categoria' => $created->categoria,
                'tipo' => $created->tipo,
                'stock_actual' => $created->stock_actual,
                'stock_minimo' => $created->stock_minimo,
                'precio_compra' => $created->precio_compra,
                'precio_venta' => $created->precio_venta,
                'active' => (bool) $created->active,
                'imagen_url' => $created->imagen ? Storage::disk('public')->url($created->imagen) : null,
            ],
        ], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'categoria' => ['sometimes', 'required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_compra' => ['sometimes', 'required', 'numeric', 'min:0'],
            'precio_venta' => ['sometimes', 'required', 'numeric', 'min:0'],
            'stock_actual' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock_minimo' => ['sometimes', 'required', 'integer', 'min:0'],
            'tipo' => ['sometimes', 'required', 'in:venta_cliente,insumo_trabajo'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'active' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('products', 'public');
        }

        $this->inventoryService->updateProduct($product, $data);
        $product->refresh();

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => [
                'id' => $product->id,
                'nombre' => $product->nombre,
                'categoria' => $product->categoria,
                'tipo' => $product->tipo,
                'stock_actual' => $product->stock_actual,
                'stock_minimo' => $product->stock_minimo,
                'precio_compra' => $product->precio_compra,
                'precio_venta' => $product->precio_venta,
                'active' => (bool) $product->active,
                'imagen_url' => $product->imagen ? Storage::disk('public')->url($product->imagen) : null,
            ],
        ]);
    }

    public function destroyProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $this->inventoryService->deleteProduct($product);

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }

    public function storeMovement(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $typeRules = ['required', 'in:entrada,salida'];
        if ($request->user()?->hasRole('recepcionista')) {
            $typeRules = ['required', 'in:salida'];
        }

        $data = $request->validate([
            'product_id' => ['required', 'string', 'exists:products,id'],
            'tipo' => $typeRules,
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'appointment_id' => ['nullable', 'string', 'exists:appointments,id'],
            'fecha' => ['nullable', 'date'],
        ], [
            'tipo.in' => 'No tienes permisos para registrar ese tipo de movimiento.',
        ]);

        try {
            $movement = $this->inventoryService->registerMovement($data, (string) $request->user()->id);
        } catch (InsufficientStockException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $movement->load(['product:id,nombre', 'user:id,name', 'appointment:id,fecha,client_id', 'appointment.client.user:id,name']);

        return response()->json([
            'message' => 'Movimiento registrado correctamente.',
            'data' => [
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
            ],
        ], 201);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }
}
