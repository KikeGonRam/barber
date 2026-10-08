<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Notifications\Concerns\PushesToDevices;
use App\Services\Order\OrderReceiptPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Aviso al cliente de que su pedido de la tienda fue marcado como entregado.
 * Se dispara desde Reception\OrderController al confirmar la entrega en
 * recepcion.
 */
class OrderDeliveredNotification extends Notification implements ShouldQueue
{
    use PushesToDevices;
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /**
     * Database siempre; correo solo si el cliente lo tiene activado.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return [...$channels, ...$this->pushChannels($notifiable)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rows = [];
        foreach ($this->order->items ?? [] as $it) {
            $rows[$it['cantidad'].'× '.$it['nombre']] = '$'.number_format($it['subtotal'] ?? ($it['precio'] * $it['cantidad']), 2);
        }

        $mail = (new MailMessage)
            ->subject('Tu pedido '.$this->order->folio.' fue entregado')
            ->markdown('emails.message', [
                'accent' => '#10b981',
                'badge' => 'Entregado',
                'title' => 'Gracias por tu compra',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Tu pedido '.$this->order->folio.' fue entregado. Gracias por confiar en UrbanBlade. Adjuntamos tu comprobante en PDF.',
                'rows' => $rows,
                'total' => ['label' => 'Total', 'value' => '$'.number_format((float) $this->order->total, 2)],
                'ctaLabel' => 'Ver mis pedidos',
                'ctaUrl' => $this->ordersUrl(),
            ]);

        // Comprobante del pedido en PDF, el mismo que se descarga en la app y la web (no crítico: si falla, el correo sale igual).
        try {
            $mail->attachData(OrderReceiptPdf::make($this->order)->output(), 'comprobante-'.$this->order->getAttribute('folio').'.pdf', ['mime' => 'application/pdf']);
        } catch (\Throwable $e) {
            Log::warning('No se pudo adjuntar el comprobante del pedido', ['order_id' => $this->order->id, 'error' => $e->getMessage()]);
        }

        return $mail;
    }

    /**
     * Payload para el canal database (centro de notificaciones in-app).
     */
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
        return config('app.frontend_url').'/my/orders';
    }
}
