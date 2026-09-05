<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\Appointment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Movimientos de inventario (entradas/salidas) para administración/recepción:
 * historial filtrable, registro de nuevos movimientos con validación de stock.
 */
class InventoryMovementController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    // Historial de movimientos con filtros de tipo, producto, rango de fecha y búsqueda libre.
    public function index(Request $request): View
    {
        $filters = $request->only(['tipo', 'product_id', 'fecha_desde', 'fecha_hasta', 'q']);

        $movements = InventoryMovement::with(['product:id,nombre', 'user:id,name'])
            ->when(! empty($filters['q']), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('nombre', 'like', '%'.$filters['q'].'%'))
                ->orWhere('motivo', 'like', '%'.$filters['q'].'%'))
            ->when(! empty($filters['tipo']), fn ($q) => $q->where('tipo', $filters['tipo']))
            ->when(! empty($filters['product_id']), fn ($q) => $q->where('product_id', $filters['product_id']))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->latest('fecha')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::query()->orderBy('nombre')->get(['id', 'nombre']);

        // Productos en o por debajo de su mínimo, para el panel de "marcar como pedido"
        // que aquí sí ven recepción/admin (a diferencia del catálogo completo, admin-only).
        $lowStockProducts = Product::where('activo', true)
            ->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])
            ->get(['nombre', 'stock_actual', 'stock_minimo', 'reabastecimiento_pedido_en']);

        $stats = [
            'total' => InventoryMovement::count(),
            'entradas' => InventoryMovement::where('tipo', 'entrada')->count(),
            'salidas' => InventoryMovement::where('tipo', 'salida')->count(),
            'hoy' => InventoryMovement::whereDate('fecha', today())->count(),
        ];

        return view('inventory.movements.index', compact('movements', 'products', 'filters', 'stats', 'lowStockProducts'));
    }

    // Formulario de nuevo movimiento: productos y últimas 50 citas (para vincular consumos a una cita).
    public function create(): View
    {
        $products = Product::query()->orderBy('nombre')->get();
        $appointments = Appointment::query()
            ->with(['client.user:id,name', 'barber.user:id,name'])
            ->orderByDesc('fecha')
            ->limit(50)
            ->get(['id', 'client_id', 'barber_id', 'fecha', 'hora_inicio']);

        return view('inventory.movements.create', compact('products', 'appointments'));
    }

    // Registra el movimiento (entrada/salida) y ajusta el stock del producto vía InventoryService.
    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        try {
            $this->inventoryService->registerMovement($request->validated(), (string) $request->user()->id);
        } catch (InsufficientStockException $exception) {
            // Salida mayor al stock disponible: se rechaza antes de escribir el movimiento.
            return back()->withInput()->withErrors(['cantidad' => $exception->getMessage()]);
        }

        return redirect()->route('inventory.movements.index')->with('status', 'Movimiento registrado correctamente.');
    }
}
