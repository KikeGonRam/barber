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
 * @param array<int, array{nombre:string, stock_actual:int, stock_minimo:int}> $products
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

        $mail = (new MailMessage)
            ->subject("Alerta de inventario: {$count} producto(s) con stock bajo")
            ->greeting('Hola '.$notifiable->name.',')
            ->line("Hay {$count} producto(s) en o por debajo de su stock minimo:");

        foreach ($this->products as $p) {
            $mail->line("- {$p['nombre']}: {$p['stock_actual']} en existencia (minimo {$p['stock_minimo']})");
        }

        return $mail
            ->action('Ver inventario', $this->inventoryUrl())
            ->line('Considera reabastecer para evitar quiebres de stock.');
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
