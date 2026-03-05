<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\Appointment;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryMovementController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['tipo', 'product_id']);

        $movements = $this->inventoryService->listMovements($filters);
        $products = Product::query()->orderBy('nombre')->get(['id', 'nombre']);

        return view('inventory.movements.index', compact('movements', 'products', 'filters'));
    }

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

    public function store(StoreInventoryMovementRequest $request): RedirectResponse
    {
        try {
            $this->inventoryService->registerMovement($request->validated(), (int) $request->user()->id);
        } catch (InsufficientStockException $exception) {
            return back()->withInput()->withErrors(['cantidad' => $exception->getMessage()]);
        }

        return redirect()->route('inventory.movements.index')->with('status', 'Movimiento registrado correctamente.');
    }
}
