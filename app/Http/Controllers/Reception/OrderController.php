<?php

namespace App\Http\Controllers\Reception;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderDeliveredNotification;
use App\Services\Order\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Controlador de recepción para la gestión de pedidos de la tienda.
 * Permite listar, entregar (con cobro), cancelar y generar recibo PDF de pedidos.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Lista paginada de pedidos con filtros por estado y búsqueda de folio,
     * más estadísticas rápidas para el panel de recepción.
     */
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
            // Suma en PHP en vez de agregación Mongo: 'total' es decimal y se castea manualmente por cada registro.
            'por_cobrar' => Order::where('estado', 'pendiente')->get(['total'])->sum(fn ($o) => (float) $o->total),
        ];

        return view('reception.orders.index', compact('orders', 'stats', 'estado', 'search'));
    }

    /**
     * Marca el pedido como entregado y registra el cobro (método de pago) en recepción.
     * Solo aplica a pedidos en estado "pendiente"; entrega y cobro ocurren en el mismo paso.
     */
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
                // La notificación es best-effort: si falla no debe revertir la entrega ya confirmada.
                Log::warning('Fallo notificacion de pedido entregado', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('status', "Pedido {$order->folio} entregado y cobrado.");
    }

    /**
     * Cancela un pedido pendiente y delega en el servicio la devolución de stock.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->estado !== 'pendiente') {
            return back()->withErrors(['order' => 'Solo se pueden cancelar pedidos pendientes.']);
        }

        // El servicio se encarga de reponer el stock de los items dentro de una transacción.
        $this->orders->cancel($order);

        return back()->with('status', "Pedido {$order->folio} cancelado y stock devuelto.");
    }

    /**
     * Recibo PDF de un pedido ya entregado.
     */
    public function receipt(Order $order): Response
    {
        // Solo se emite recibo de pedidos ya entregados; en cualquier otro estado no existe cobro que respaldar.
        abort_if($order->estado !== 'entregado', 404);

        $order->loadMissing('client.user');

        $pdf = Pdf::loadView('pdf.order-receipt', [
            'folio' => $order->folio,
            'emitido' => optional($order->entregado_en ?? $order->created_at)->format('d/m/Y'),
            'cliente' => $order->client?->user?->name ?? 'Cliente',
            'items' => $order->items ?? [],
            'total' => (float) $order->total,
            'metodo' => ucfirst($order->metodo_pago ?? '—'),
        ]);

        return $pdf->download('pedido-'.$order->folio.'.pdf');
    }
}
