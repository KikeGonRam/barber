<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\Order\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly OrderService $orders,
    ) {}

    public function index(): View
    {
        return view('client.cart.index', [
            'items' => $this->cart->items(),
            'total' => $this->cart->total(),
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'cantidad' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! $product->isSellable()) {
            return back()->withErrors(['cart' => 'Ese producto no está disponible.']);
        }

        $this->cart->add($product, (int) ($validated['cantidad'] ?? 1));

        return back()->with('status', 'Producto agregado al carrito.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:0'],
        ]);

        $this->cart->update((string) $validated['product_id'], (int) $validated['cantidad']);

        return back();
    }

    public function remove(string $productId): RedirectResponse
    {
        $this->cart->remove($productId);

        return back()->with('status', 'Producto eliminado del carrito.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client, 403);

        if ($this->cart->isEmpty()) {
            return redirect()->route('client.carrito.index')->withErrors(['cart' => 'Tu carrito está vacío.']);
        }

        $items = array_map(fn ($i) => [
            'product_id' => $i['product_id'],
            'nombre' => $i['nombre'],
            'precio' => $i['precio'],
            'cantidad' => $i['cantidad'],
        ], array_values($this->cart->items()));

        try {
            $order = $this->orders->place($client, $items, 'tienda');
        } catch (InsufficientStockException $e) {
            return redirect()->route('client.carrito.index')->withErrors(['cart' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return redirect()->route('client.carrito.index')->withErrors(['cart' => 'No se pudo completar el pedido. Intenta de nuevo.']);
        }

        $this->cart->clear();

        return redirect()->route('client.pedidos.index')
            ->with('status', "Pedido {$order->folio} creado. Paga y recoge en sucursal.");
    }
}
