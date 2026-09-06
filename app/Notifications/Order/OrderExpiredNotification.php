<?php

namespace App\Notifications\Order;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al cliente de que su pedido "paga y recoge en sucursal" se canceló
 * automáticamente por no pasar a recogerlo (ver CancelExpiredOrdersCommand),
 * para que no se presente en sucursal esperando un pedido que ya no existe.
 */
class OrderExpiredNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('Tu pedido '.$this->order->folio.' fue cancelado')
            ->markdown('emails.message', [
                'accent' => '#ef4444',
                'badge' => 'Cancelado',
                'title' => 'Hola '.$notifiable->name.',',
                'intro' => 'Tu pedido '.$this->order->folio.' se canceló automáticamente por no haberse recogido en sucursal. Si aún lo quieres, puedes volver a generarlo desde la tienda.',
                'rows' => [
                    'Folio' => $this->order->folio,
                    'Total' => '$'.number_format((float) $this->order->total, 2),
                ],
                'ctaLabel' => 'Ir a la tienda',
                'ctaUrl' => $this->storeUrl(),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_expired',
            'order_id' => $this->order->id,
            'folio' => $this->order->folio,
            'title' => 'Pedido cancelado',
            'message' => 'Tu pedido '.$this->order->folio.' se canceló por no recogerse a tiempo.',
            'total' => (float) $this->order->total,
            'url' => $this->storeUrl(),
        ];
    }

    private function storeUrl(): string
    {
        return config('app.frontend_url').'/store';
    }
}
