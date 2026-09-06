<?php

namespace App\Http\Controllers\Api\Order;

use App\Exceptions\Domain\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\Order\OrderDeliveredNotification;
use App\Services\Order\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Pedidos de productos: bandeja de recepción/administración (todos los
 * pedidos) y autoservicio de cliente (los propios), puerto de
 * Reception\OrderController + Client\OrderController + Client\CartController
 * (web). El carrito en sí vive en el frontend Nuxt (localStorage) — a
 * diferencia de la versión Blade, que lo guarda en sesión, un cliente
 * Bearer-token no tiene sesión de servidor; el checkout manda los items
 * directo aquí y OrderService::place() sigue siendo la única fuente de
 * verdad del precio (nunca se confía en un precio que mande el cliente).
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Lista pedidos: un cliente ve solo los suyos; admin/recepción ven
     * todos, con filtros opcionales (estado/folio) y las mismas
     * estadísticas rápidas que Reception\OrderController::index() (web).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user?->hasAnyRole(['administrador', 'recepcionista']);

        $query = Order::query()->with('client.user:id,name')->latest();

        if ($isStaff) {
            $estado = (string) $request->query('estado', '');
            $search = trim((string) $request->query('q', ''));
            $query->when($estado !== '', fn ($q) => $q->where('estado', $estado))
                ->when($search !== '', fn ($q) => $q->where('folio', 'like', '%'.strtoupper($search).'%'));
        } elseif ($user?->clientProfile) {
            $query->where('client_id', (string) $user->clientProfile->id);
        } else {
            abort(403, 'No autorizado para consultar pedidos.');
        }

        $orders = $query->paginate(20)->withQueryString();

        $payload = [
            'data' => collect($orders->items())->map(fn (Order $o) => $this->present($o))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ];

        if ($isStaff) {
            $payload['meta']['stats'] = [
                'pendientes' => Order::where('estado', 'pendiente')->count(),
                'entregados' => Order::where('estado', 'entregado')->count(),
                'por_cobrar' => (float) Order::where('estado', 'pendiente')->get(['total'])->sum(fn ($o) => (float) $o->total),
            ];
        }

        return response()->json($payload);
    }

    /**
     * Crea un pedido de tienda a partir de los items del carrito (armado en
     * el frontend). Solo cliente. El precio de cada línea NUNCA se toma del
     * request: OrderService::place() relee Product::precio_venta.
     *
     * @bodyParam items array required Lista de {product_id, cantidad}.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $client = $user?->clientProfile;
        abort_if(! $client, 403, 'No tienes perfil de cliente.');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.cantidad' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = $this->orders->place($client, $validated['items'], 'tienda');
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Pedido {$order->folio} creado. Paga y recoge en sucursal.",
            'data' => $this->present($order),
        ], 201);
    }

    /**
     * Cancela un pedido pendiente y devuelve el stock (delegado en
     * OrderService::cancel()). El cliente solo puede cancelar el suyo;
     * admin/recepción pueden cancelar cualquiera.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user?->hasAnyRole(['administrador', 'recepcionista']);
        $isOwner = $user?->clientProfile && (string) $order->client_id === (string) $user->clientProfile->id;

        abort_if(! $isStaff && ! $isOwner, 403);

        if ($order->estado !== 'pendiente') {
            return response()->json(['message' => 'Solo se pueden cancelar pedidos pendientes.'], 422);
        }

        $this->orders->cancel($order);

        return response()->json([
            'message' => "Pedido {$order->folio} cancelado y stock devuelto.",
            'data' => $this->present($order->fresh()),
        ]);
    }

    /**
     * Marca un pedido como entregado y registra el método de cobro (staff).
     */
    public function deliver(Request $request, Order $order): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');

        if ($order->estado !== 'pendiente') {
            return response()->json(['message' => 'Solo se pueden entregar pedidos pendientes.'], 422);
        }

        $validated = $request->validate([
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia,qr'],
        ]);

        $order->update([
            'estado' => 'entregado',
            'metodo_pago' => $validated['metodo_pago'],
            'entregado_en' => now(),
        ]);

        if ($clientUser = $order->client?->user) {
            try {
                $clientUser->notify(new OrderDeliveredNotification($order));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de pedido entregado', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'message' => "Pedido {$order->folio} entregado y cobrado.",
            'data' => $this->present($order->fresh()),
        ]);
    }

    /**
     * Recibo PDF de un pedido entregado (staff). A diferencia de los pagos,
     * Order no cachea la ruta del PDF generado (no hay campo equivalente a
     * comprobante_pdf) — se regenera en cada descarga y se devuelve el
     * binario directo, no una URL pública.
     */
    public function receipt(Request $request, Order $order): Response
    {
        abort_unless($request->user()?->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
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

    /**
     * Shape compartido por index/store/cancel/deliver — castea 'total' (y
     * el precio/subtotal de cada línea) a float explícito: Order::$total
     * usa el cast decimal:2 de Laravel, que serializa como string y ya
     * causó un bug real de '$NaN' en el frontend (ver Fase 9.3, Pagos).
     */
    private function present(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'folio' => $order->folio,
            'estado' => $order->estado,
            'tipo' => $order->tipo,
            'total' => (float) $order->total,
            'metodo_pago' => $order->metodo_pago,
            'entregado_en' => optional($order->entregado_en)->toIso8601String(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'items' => collect($order->items ?? [])->map(fn ($i) => [
                'product_id' => $i['product_id'] ?? null,
                'nombre' => $i['nombre'] ?? null,
                'precio' => (float) ($i['precio'] ?? 0),
                'cantidad' => (int) ($i['cantidad'] ?? 0),
                'subtotal' => (float) ($i['subtotal'] ?? 0),
            ])->values(),
            'client' => [
                'id' => $order->client?->id,
                'name' => $order->client?->user?->name,
            ],
        ];
    }
}
