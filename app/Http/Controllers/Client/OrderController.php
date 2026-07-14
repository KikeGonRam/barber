<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): View
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client, 403);

        $orders = Order::query()
            ->where('client_id', (string) $client->id)
            ->latest()
            ->paginate(15);

        return view('client.orders.index', compact('orders'));
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client || (string) $order->client_id !== (string) $client->id, 403);

        if ($order->estado !== 'pendiente') {
            return back()->withErrors(['order' => 'Solo se pueden cancelar pedidos pendientes.']);
        }

        $this->orders->cancel($order);

        return back()->with('status', 'Pedido cancelado y stock devuelto.');
    }
}
