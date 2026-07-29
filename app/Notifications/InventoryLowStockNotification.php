<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a administracion/recepcion de productos por debajo de su stock minimo.
 * Recibe un arreglo simple (no modelos) para ser seguro en la cola.
 *
 * @param  array<int, array{nombre:string, stock_actual:int, stock_minimo:int}>  $products
 */
class InventoryLowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $products) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->products);

        $rows = [];
        foreach ($this->products as $p) {
            $existencia = $p['stock_actual'] <= 0 ? 'Agotado' : $p['stock_actual'].' en existencia';
            $rows[$p['nombre']] = $existencia.' (min '.$p['stock_minimo'].')';
        }

        return (new MailMessage)
            ->subject("Alerta de inventario: {$count} producto(s) con stock bajo")
            ->markdown('emails.message', [
                'accent' => '#ef4444',
                'badge' => $count.' producto(s)',
                'title' => 'Stock bajo minimo',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Estos productos estan en o por debajo de su stock minimo. Considera reabastecer.',
                'rows' => $rows,
                'ctaLabel' => 'Ver inventario',
                'ctaUrl' => $this->inventoryUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inventory_low_stock',
            'title' => 'Inventario bajo',
            'message' => count($this->products).' producto(s) por debajo del stock minimo.',
            'count' => count($this->products),
            'products' => $this->products,
            'url' => $this->inventoryUrl(),
        ];
    }

    private function inventoryUrl(): string
    {
        try {
            return route('inventory.products.index');
        } catch (\Throwable $e) {
            return url('/');
        }
    }
}
