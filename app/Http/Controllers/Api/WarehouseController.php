<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $inventories = Inventory::query()->latest('id')->paginate(15)->withQueryString();

        return response()->json([
            'data' => $inventories->getCollection()->map(fn (Inventory $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'min_stock' => $item->min_stock,
                'price' => $item->price,
                'description' => $item->description,
                'active' => (bool) $item->active,
                'imagen' => $item->imagen,
                'low_stock' => (int) $item->quantity <= (int) $item->min_stock,
            ])->values(),
            'meta' => [
                'current_page' => $inventories->currentPage(),
                'last_page' => $inventories->lastPage(),
                'total' => $inventories->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('inventory', 'public');
        }

        $created = Inventory::create($data);

        return response()->json([
            'message' => 'Inventario creado correctamente.',
            'data' => [
                'id' => $created->id,
                'name' => $created->name,
                'quantity' => $created->quantity,
                'min_stock' => $created->min_stock,
                'price' => $created->price,
                'description' => $created->description,
                'active' => (bool) $created->active,
                'imagen' => $created->imagen,
            ],
        ], 201);
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'min_stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('inventory', 'public');
        }

        $inventory->update($data);

        return response()->json([
            'message' => 'Inventario actualizado correctamente.',
            'data' => [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'quantity' => $inventory->quantity,
                'min_stock' => $inventory->min_stock,
                'price' => $inventory->price,
                'description' => $inventory->description,
                'active' => (bool) $inventory->active,
                'imagen' => $inventory->imagen,
            ],
        ]);
    }

    public function destroy(Request $request, Inventory $inventory): JsonResponse
    {
        $this->authorizeAdmin($request);

        $inventory->delete();

        return response()->json([
            'message' => 'Inventario eliminado correctamente.',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
