<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\Order\OrderExpiredNotification;
use App\Services\Order\OrderService;
use Illuminate\Console\Command;

/**
 * Cancela pedidos "pendiente" (paga y recoge en sucursal) que llevan más de
 * DIAS_ABANDONO sin recogerse, devolviendo el stock reservado. Antes de esto
 * un pedido nunca cobrado ni cancelado a mano dejaba su stock bloqueado para
 * siempre — inventario reservado que en realidad nunca se iba a vender.
 */
class CancelExpiredOrdersCommand extends Command
{
    const DIAS_ABANDONO = 3;

    protected $signature = 'orders:cancel-expired {--dry-run : Solo muestra cuantos pedidos se cancelarian, sin escribir}';

    protected $description = 'Cancela pedidos pendientes no recogidos en sucursal y devuelve su stock';

    public function handle(OrderService $orders): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $expirados = Order::where('estado', 'pendiente')
            ->where('created_at', '<', now()->subDays(self::DIAS_ABANDONO))
            ->with('client.user')
            ->get();

        if ($expirados->isEmpty()) {
            $this->info('Sin pedidos pendientes vencidos.');

            return self::SUCCESS;
        }

        $this->info("{$expirados->count()} pedido(s) pendiente(s) con más de ".self::DIAS_ABANDONO.' días sin recogerse.');

        if ($dryRun) {
            return self::SUCCESS;
        }

        foreach ($expirados as $order) {
            $orders->cancel($order);

            $user = $order->client?->user;
            if ($user) {
                try {
                    $user->notify(new OrderExpiredNotification($order));
                } catch (\Throwable) {
                }
            }
        }

        $this->info("{$expirados->count()} pedido(s) cancelados y stock devuelto.");

        return self::SUCCESS;
    }
}
