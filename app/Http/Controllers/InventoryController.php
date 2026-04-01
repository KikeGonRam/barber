<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryStoreRequest;
use App\Http\Requests\InventoryUpdateRequest;
use App\Models\Inventory;
use App\Services\BusinessEventService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InventoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly BusinessEventService $businessEventService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Inventory::class);
        $inventories = Inventory::paginate(15);
        $lowStockCount = $inventories->filter(fn ($inv) => $inv->quantity <= $inv->min_stock)->count();

        if ($lowStockCount > 0) {
            $this->businessEventService->record('alerts', 'low_stock_detected', [
                'low_stock_count' => $lowStockCount,
                'page_items' => $inventories->count(),
            ]);
        }

        return view('almacen.index', compact('inventories', 'lowStockCount'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Inventory::class);

        return view('almacen.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InventoryStoreRequest $request)
    {
        $this->authorize('create', Inventory::class);
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('inventory', 'public');
        }

        $inventory = Inventory::create($data);

        return redirect()->route('almacen.index')->with('success', 'Inventario creado correctamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Inventory $inventory)
    {
        $this->authorize('view', $inventory);

        return view('almacen.show', compact('inventory'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Inventory $inventory)
    {
        $this->authorize('update', $inventory);

        return view('almacen.edit', compact('inventory'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InventoryUpdateRequest $request, Inventory $inventory)
    {
        $this->authorize('update', $inventory);
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('inventory', 'public');
        }

        $inventory->update($data);

        return redirect()->route('almacen.index')->with('success', 'Inventario actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventory $inventory)
    {
        $this->authorize('delete', $inventory);
        $inventory->delete();

        return redirect()->route('almacen.index')->with('success', 'Inventario eliminado correctamente.');
    }
}
