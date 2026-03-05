<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['categoria', 'tipo']);

        $products = $this->inventoryService->listProducts($filters);
        $lowStockCount = $this->inventoryService->lowStockCount();

        return view('inventory.products.index', compact('products', 'filters', 'lowStockCount'));
    }

    public function create(): View
    {
        return view('inventory.products.create');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->inventoryService->createProduct($request->validated());

        return redirect()->route('inventory.products.index')->with('status', 'Producto creado correctamente.');
    }

    public function edit(Product $product): View
    {
        return view('inventory.products.edit', compact('product'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->inventoryService->updateProduct($product, $request->validated());

        return redirect()->route('inventory.products.index')->with('status', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->inventoryService->deleteProduct($product);

        return redirect()->route('inventory.products.index')->with('status', 'Producto eliminado correctamente.');
    }
}
