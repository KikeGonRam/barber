<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\Sortable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Catálogo de productos para administración/inventario: listado filtrable con
 * alerta de bajo stock, alta, edición y baja de productos.
 */
class ProductController extends Controller
{
    use Sortable;

    public function __construct(private readonly InventoryService $inventoryService) {}

    // Listado de productos con filtros; el filtro de bajo stock compara dos columnas del propio documento.
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'categoria', 'tipo', 'bajo_stock']);

        $query = Product::query()
            ->when(! empty($filters['q']), fn ($q) => $q
                ->where('nombre', 'like', '%'.$filters['q'].'%')
                ->orWhere('descripcion', 'like', '%'.$filters['q'].'%'))
            ->when(! empty($filters['categoria']), fn ($q) => $q->where('categoria', $filters['categoria']))
            ->when(! empty($filters['tipo']), fn ($q) => $q->where('tipo', $filters['tipo']))
            // whereRaw con $expr: comparar stock_actual <= stock_minimo (dos campos del mismo
            // documento) no se puede expresar con los operadores where() normales de MongoDB.
            ->when(! empty($filters['bajo_stock']), fn ($q) => $q->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]]));

        // nombre = alfabetico, stock_actual/precio_compra/precio_venta = numerico.
        $products = $this->applySort(
            $query,
            $request,
            ['nombre', 'stock_actual', 'precio_compra', 'precio_venta'],
            'nombre',
            'asc'
        )
            ->paginate(20)
            ->withQueryString();

        $lowStockCount = $this->inventoryService->lowStockCount();

        $categorias = Product::distinct()->pluck('categoria')->filter()->sort()->values();
        $tipos = Product::distinct()->pluck('tipo')->filter()->sort()->values();

        $stats = [
            'total' => Product::count(),
            'bajo_stock' => $lowStockCount,
            'valor_total' => Product::get(['stock_actual', 'precio_compra'])->sum(fn ($p) => (float) $p->stock_actual * (float) $p->precio_compra),
        ];

        return view('inventory.products.index', compact('products', 'filters', 'lowStockCount', 'categorias', 'tipos', 'stats'));
    }

    public function create(): View
    {
        return view('inventory.products.create');
    }

    // Crea el producto, subiendo la imagen a storage público si se envió una.
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('products', 'public');
        }

        $this->inventoryService->createProduct($data);

        return redirect()->route('inventory.products.index')->with('status', 'Producto creado correctamente.');
    }

    public function edit(Product $product): View
    {
        return view('inventory.products.edit', compact('product'));
    }

    // Actualiza los datos del producto y reemplaza la imagen si se sube una nueva.
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            if (! empty($product->imagen) && Storage::disk('public')->exists($product->imagen)) {
                Storage::disk('public')->delete($product->imagen);
            }

            $data['imagen'] = $request->file('imagen')->store('products', 'public');
        }

        $this->inventoryService->updateProduct($product, $data);

        return redirect()->route('inventory.products.index')->with('status', 'Producto actualizado correctamente.');
    }

    // Elimina el producto (la lógica de si bloquear por movimientos/pedidos existentes vive en InventoryService).
    public function destroy(Product $product): RedirectResponse
    {
        $this->inventoryService->deleteProduct($product);

        return redirect()->route('inventory.products.index')->with('status', 'Producto eliminado correctamente.');
    }
}
