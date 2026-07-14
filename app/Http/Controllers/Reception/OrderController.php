<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderDeliveredNotification;
use App\Services\Order\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): View
    {
        $estado = (string) $request->query('estado', '');
        $search = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->with('client.user:id,name')
            ->when($estado !== '', fn ($q) => $q->where('estado', $estado))
            ->when($search !== '', fn ($q) => $q->where('folio', 'like', '%'.strtoupper($search).'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'pendientes' => Order::where('estado', 'pendiente')->count(),
            'entregados' => Order::where('estado', 'entregado')->count(),
            'por_cobrar' => Order::where('estado', 'pendiente')->get(['total'])->sum(fn ($o) => (float) $o->total),
        ];

        return view('reception.orders.index', compact('orders', 'stats', 'estado', 'search'));
    }

    public function deliver(Request $request, Order $order): RedirectResponse
    {
        if ($order->estado !== 'pendiente') {
            return back()->withErrors(['order' => 'Solo se pueden entregar pedidos pendientes.']);
        }

        $data = $request->validate([
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia,qr'],
        ]);

        $order->update([
            'estado' => 'entregado',
            'metodo_pago' => $data['metodo_pago'],
            'entregado_en' => now(),
        ]);

        // Avisar al cliente que su pedido fue entregado.
        $user = $order->client?->user;
        if ($user) {
            try {
                $user->notify(new OrderDeliveredNotification($order));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de pedido entregado', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('status', "Pedido {$order->folio} entregado y cobrado.");
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->estado !== 'pendiente') {
            return back()->withErrors(['order' => 'Solo se pueden cancelar pedidos pendientes.']);
        }

        $this->orders->cancel($order);

        return back()->with('status', "Pedido {$order->folio} cancelado y stock devuelto.");
    }
}
