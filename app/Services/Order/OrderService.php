<?php

namespace App\Services\Order;

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Str;

/**
 * Orquesta la creacion y cancelacion de pedidos (tienda o productos de
 * cita), coordinando la reserva/devolucion de stock con InventoryService
 * para mantener trazabilidad de cada movimiento de inventario.
 */
class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Crea un pedido a partir de items, reservando (descontando) stock con
     * trazabilidad. Valida stock de todo antes de descontar.
     *
     * El precio de cada linea NUNCA se toma de $items (el llamador puede ser
     * un request HTTP, p.ej. el wizard de agregar productos a una cita, y no
     * hay que confiar en un precio que venga del cliente para mover dinero e
     * inventario reales) — siempre se relee Product::precio_venta aqui mismo,
     * asi que 'precio' en $items se ignora aunque venga presente.
     *
     * @param  array<int, array{product_id:string,nombre?:string,cantidad?:int}>  $items
     */
    public function place(Client $client, array $items, string $tipo = 'tienda', ?string $appointmentId = null): Order
    {
        $items = array_values(array_filter($items, fn ($i) => (int) ($i['cantidad'] ?? 0) > 0));

        if (empty($items)) {
            throw new \RuntimeException('El pedido no tiene productos.');
        }

        // 1) Validar stock de todos los productos antes de descontar, y
        // capturar el precio real y vigente de cada uno (fuente de verdad
        // unica, sin importar que precio haya mandado el llamador).
        $products = [];
        foreach ($items as $it) {
            $product = Product::find($it['product_id']);
            if (! $product || ! $product->isSellable()) {
                throw new \RuntimeException('Un producto ya no está disponible.');
            }
            if ((int) $product->stock_actual < (int) $it['cantidad']) {
                throw new InsufficientStockException("Stock insuficiente para {$product->nombre}.");
            }
            $products[(string) $it['product_id']] = $product;
        }

        // 2) Descontar stock (cada movimiento es atomico y deja trazabilidad).
        $userId = (string) ($client->user_id ?? '');
        $motivo = $tipo === 'cita' ? 'Productos de cita' : 'Pedido de tienda';
        $lines = [];
        $total = 0.0;

        foreach ($items as $it) {
            $product = $products[(string) $it['product_id']];
            $qty = (int) $it['cantidad'];
            $precio = (float) $product->precio_venta;
            $subtotal = $precio * $qty;

            // Descuenta stock por cada linea: efecto secundario que persiste
            // un movimiento de inventario (salida) con trazabilidad.
            $this->inventory->registerMovement([
                'product_id' => $it['product_id'],
                'cantidad' => $qty,
                'tipo' => 'salida',
                'motivo' => $motivo,
                'appointment_id' => $appointmentId,
            ], $userId);

            $lines[] = [
                'product_id' => (string) $it['product_id'],
                'nombre' => (string) $product->nombre,
                'precio' => $precio,
                'cantidad' => $qty,
                'subtotal' => $subtotal,
            ];
            $total += $subtotal;
        }

        // 3) Crear el pedido.
        return Order::create([
            'client_id' => (string) $client->id,
            'folio' => 'P-'.strtoupper(Str::random(6)),
            'items' => $lines,
            'total' => $total,
            'estado' => 'pendiente',
            'tipo' => $tipo,
            'appointment_id' => $appointmentId,
        ]);
    }

    /**
     * Cancela un pedido pendiente y devuelve el stock. No hace nada si el
     * pedido ya no esta en estado "pendiente" (evita cancelar dos veces o
     * revertir stock de un pedido ya entregado/cancelado).
     */
    public function cancel(Order $order): void
    {
        if ($order->estado !== 'pendiente') {
            return;
        }

        $userId = (string) ($order->client?->user_id ?? '');

        foreach ($order->items ?? [] as $it) {
            try {
                $this->inventory->registerMovement([
                    'product_id' => $it['product_id'],
                    'cantidad' => (int) $it['cantidad'],
                    'tipo' => 'entrada',
                    'motivo' => 'Cancelacion de pedido',
                ], $userId);
            } catch (\Throwable $e) {
                // el producto pudo eliminarse; continuar
            }
        }

        // Marca el pedido como cancelado solo despues de intentar devolver
        // el stock de todas las lineas.
        $order->update(['estado' => 'cancelado']);
    }
}
