<?php

namespace App\Http\Controllers\Api\Inventory;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        $filters = $request->only(['q', 'categoria', 'tipo', 'bajo_stock']);
        $products = $this->inventoryService->listProducts($filters, 20);

        // distinct()->pluck() no funciona con el driver de MongoDB (devuelve
        // solo null por documento); se deduplica en PHP, igual que
        // Inventory\ProductController::index() (web).
        $categorias = Product::pluck('categoria')->filter()->unique()->sort()->values();
        $tipos = Product::pluck('tipo')->filter()->unique()->sort()->values();

        return response()->json([
            'data' => collect($products->items())
                ->map(fn (Product $product) => $this->productPayload($product))
                ->values(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
                'stats' => [
                    'total' => Product::count(),
                    'bajo_stock' => $this->inventoryService->lowStockCount(),
                    'valor_total' => (float) Product::get(['stock_actual', 'precio_compra'])->sum(fn ($p) => (float) $p->stock_actual * (float) $p->precio_compra),
                ],
                'categorias' => $categorias,
                'tipos' => $tipos,
            ],
        ]);
    }

    /**
     * Productos en o por debajo de su stock mínimo, para el panel de
     * "marcar como pedido" (silencia la alerta diaria unos días) — visto
     * tanto en el catálogo de productos como en movimientos (web).
     */
    public function lowStock(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $products = Product::where('activo', true)
            ->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])
            ->get(['nombre', 'stock_actual', 'stock_minimo', 'reabastecimiento_pedido_en']);

        return response()->json([
            'data' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'stock_actual' => $p->stock_actual,
                'stock_minimo' => $p->stock_minimo,
                'pending_restock' => $p->hasPendingRestockOrder(),
                'reabastecimiento_pedido_en' => optional($p->reabastecimiento_pedido_en)->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Marca un producto de stock bajo como "ya pedido" para silenciar la
     * alerta diaria/panel durante Product::RESTOCK_GRACE_DAYS.
     */
    public function markProductOrdered(Request $request, Product $product): JsonResponse
    {
        $this->authorizeStaff($request);

        $this->inventoryService->markProductOrdered($product, (string) $request->user()->id);

        return response()->json([
            'message' => 'Producto marcado como pedido.',
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

        $filters = $request->only(['q', 'tipo', 'product_id', 'fecha_desde', 'fecha_hasta']);
        $movements = $this->inventoryService->listMovements($filters, 20);

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
                'stats' => [
                    'total' => InventoryMovement::count(),
                    'entradas' => InventoryMovement::where('tipo', 'entrada')->count(),
                    'salidas' => InventoryMovement::where('tipo', 'salida')->count(),
                    'hoy' => InventoryMovement::whereDate('fecha', today())->count(),
                ],
            ],
        ]);
    }

    // Crea un producto nuevo (solo administrador); sube la imagen a storage público si se envía
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
            'tipo' => ['required', Rule::in([...Product::SALE_TYPES, ...Product::SUPPLY_TYPES])],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'active' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('products', 'public');
        }

        $created = $this->inventoryService->createProduct($data);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => $this->productPayload($created),
        ], 201);
    }

    // Actualiza un producto (solo administrador); todos los campos son "sometimes" para permitir updates parciales
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
            'tipo' => ['sometimes', 'required', Rule::in([...Product::SALE_TYPES, ...Product::SUPPLY_TYPES])],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'active' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('products', 'public');
        }

        $this->inventoryService->updateProduct($product, $data);
        $product->refresh();

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => $this->productPayload($product),
        ]);
    }

    // Elimina un producto (solo administrador)
    public function destroyProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorizeAdmin($request);

        $this->inventoryService->deleteProduct($product);

        return response()->json([
            'message' => 'Producto eliminado correctamente.',
        ]);
    }

    // Registra un movimiento de stock; delega el ajuste de stock_actual y validación de stock suficiente al servicio
    public function storeMovement(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        // La recepción solo puede registrar salidas (consumo de productos), no entradas (reposición de stock)
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

    // Aborta con 403 si no hay usuario autenticado o no tiene rol administrador
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }

    // Aborta con 403 si no hay usuario autenticado o no tiene rol administrador/recepcionista
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }

    // Serializa un producto a array de respuesta; low_stock e imagen_url se calculan aquí para no repetirlo en cada endpoint
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'nombre' => $product->nombre,
            'categoria' => $product->categoria,
            'descripcion' => $product->descripcion,
            'tipo' => $product->tipo,
            'stock_actual' => $product->stock_actual,
            'stock_minimo' => $product->stock_minimo,
            // precio_compra/precio_venta usan el cast decimal:2 de Laravel, que
            // serializa como string ("120.00") — castear a float explícito aquí
            // evita el mismo bug de "$NaN" ya encontrado con Payment/Order
            // (ver frontend-urban, Fases 9.3/9.4).
            'precio_compra' => (float) $product->precio_compra,
            'precio_venta' => (float) $product->precio_venta,
            'activo' => $product->isActive(),
            'active' => $product->isActive(),
            'low_stock' => (int) $product->stock_actual <= (int) $product->stock_minimo,
            'pending_restock' => $product->hasPendingRestockOrder(),
            'imagen_url' => $product->imagen ? Storage::disk('public')->url($product->imagen) : null,
        ];
    }
}
