<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\WarehouseStoreRequest;
use App\Http\Requests\Warehouse\WarehouseUpdateRequest;
use App\Models\Inventory;
use App\Services\BusinessEventService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WarehouseController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly BusinessEventService $businessEventService) {}

    public function index(\Illuminate\Http\Request $request)
    {
        $this->authorize('viewAny', Inventory::class);

        $filters = $request->only(['q', 'active', 'bajo_stock']);
        $search  = trim((string) ($filters['q'] ?? ''));
        $active  = (string) ($filters['active'] ?? '');

        $query = Inventory::query()
            ->when($search !== '', fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%"))
            ->when($active !== '', fn($q) => $q->where('active', $active === '1'))
            ->when(!empty($filters['bajo_stock']), fn($q) => $q->whereRaw(['$expr' => ['$lte' => ['$quantity', '$min_stock']]]))
            ->orderBy('name');

        $inventories   = $query->paginate(15)->withQueryString();
        $lowStockCount = Inventory::whereRaw(['$expr' => ['$lte' => ['$quantity', '$min_stock']]])->count();

        if ($lowStockCount > 0) {
            $this->businessEventService->record('alerts', 'low_stock_detected', [
                'low_stock_count' => $lowStockCount,
                'page_items'      => $inventories->count(),
            ]);
        }

        $stats = [
            'total'      => Inventory::count(),
            'activos'    => Inventory::where('active', true)->count(),
            'inactivos'  => Inventory::where('active', false)->count(),
            'bajo_stock' => $lowStockCount,
            'valor_total'=> (float) Inventory::get(['quantity', 'price'])->sum(fn($i) => (float)$i->quantity * (float)$i->price),
        ];

        return view('warehouse.index', compact('inventories', 'lowStockCount', 'filters', 'stats'));
    }

    public function create()
    {
        $this->authorize('create', Inventory::class);

        return view('warehouse.create');
    }

    public function store(WarehouseStoreRequest $request)
    {
        $this->authorize('create', Inventory::class);
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('inventory', 'public');
        }

        Inventory::create($data);

        return redirect()->route('warehouse.index')->with('success', 'Inventario creado correctamente.');
    }

    public function show(Inventory $inventory)
    {
        $this->authorize('view', $inventory);

        return view('warehouse.show', compact('inventory'));
    }

    public function edit(Inventory $inventory)
    {
        $this->authorize('update', $inventory);

        return view('warehouse.edit', compact('inventory'));
    }

    public function update(WarehouseUpdateRequest $request, Inventory $inventory)
    {
        $this->authorize('update', $inventory);
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('inventory', 'public');
        }

        $inventory->update($data);

        return redirect()->route('warehouse.index')->with('success', 'Inventario actualizado correctamente.');
    }

    public function destroy(Inventory $inventory)
    {
        $this->authorize('delete', $inventory);
        $inventory->delete();

        return redirect()->route('warehouse.index')->with('success', 'Inventario eliminado correctamente.');
    }
}
