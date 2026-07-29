<?php

namespace App\Services\Order;

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Crea un pedido a partir de items, reservando (descontando) stock con
     * trazabilidad. Valida stock de todo antes de descontar.
     *
     * @param  array<int, array{product_id:string,nombre:string,precio:float|int,cantidad:int}>  $items
     */
    public function place(Client $client, array $items, string $tipo = 'tienda', ?string $appointmentId = null): Order
    {
        $items = array_values(array_filter($items, fn ($i) => (int) ($i['cantidad'] ?? 0) > 0));

        if (empty($items)) {
            throw new \RuntimeException('El pedido no tiene productos.');
        }

        // 1) Validar stock de todos los productos antes de descontar.
        foreach ($items as $it) {
            $product = Product::find($it['product_id']);
            if (! $product || ! $product->isSellable()) {
                throw new \RuntimeException('Un producto ya no está disponible.');
            }
            if ((int) $product->stock_actual < (int) $it['cantidad']) {
                throw new InsufficientStockException("Stock insuficiente para {$product->nombre}.");
            }
        }

        // 2) Descontar stock (cada movimiento es atomico y deja trazabilidad).
        $userId = (string) ($client->user_id ?? '');
        $motivo = $tipo === 'cita' ? 'Productos de cita' : 'Pedido de tienda';
        $lines = [];
        $total = 0.0;

        foreach ($items as $it) {
            $qty = (int) $it['cantidad'];
            $precio = (float) $it['precio'];
            $subtotal = $precio * $qty;

            $this->inventory->registerMovement([
                'product_id' => $it['product_id'],
                'cantidad' => $qty,
                'tipo' => 'salida',
                'motivo' => $motivo,
                'appointment_id' => $appointmentId,
            ], $userId);

            $lines[] = [
                'product_id' => (string) $it['product_id'],
                'nombre' => (string) $it['nombre'],
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
     * Cancela un pedido pendiente y devuelve el stock.
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

        $order->update(['estado' => 'cancelado']);
    }
}
