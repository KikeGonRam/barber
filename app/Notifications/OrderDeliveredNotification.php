<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

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
        $rows = [];
        foreach ($this->order->items ?? [] as $it) {
            $rows[$it['cantidad'].'× '.$it['nombre']] = '$'.number_format($it['subtotal'] ?? ($it['precio'] * $it['cantidad']), 2);
        }

        return (new MailMessage)
            ->subject('Tu pedido '.$this->order->folio.' fue entregado')
            ->markdown('emails.message', [
                'accent' => '#10b981',
                'badge' => 'Entregado',
                'title' => 'Gracias por tu compra',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Tu pedido '.$this->order->folio.' fue entregado. Gracias por confiar en UrbanBlade.',
                'rows' => $rows,
                'total' => ['label' => 'Total', 'value' => '$'.number_format((float) $this->order->total, 2)],
                'ctaLabel' => 'Ver mis pedidos',
                'ctaUrl' => $this->ordersUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_delivered',
            'order_id' => $this->order->id,
            'folio' => $this->order->folio,
            'title' => 'Pedido entregado',
            'message' => 'Tu pedido '.$this->order->folio.' fue entregado.',
            'total' => (float) $this->order->total,
            'url' => $this->ordersUrl(),
        ];
    }

    private function ordersUrl(): string
    {
        try {
            return route('client.pedidos.index');
        } catch (\Throwable $e) {
            return url('/');
        }
    }
}
