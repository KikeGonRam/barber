<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('in_app')) {
            $channels[] = 'database';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return $channels ?: ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->payment->id ? route('payments.receipt.download', $this->payment) : url('/dashboard');

        return (new MailMessage)
            ->subject('Comprobante de pago #'.$this->payment->id)
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Se registró correctamente tu pago.')
            ->line('Monto: $'.number_format((float) $this->payment->monto, 2))
            ->line('Propina: $'.number_format((float) $this->payment->propina, 2))
            ->line('Método: '.$this->payment->metodo_pago)
            ->action('Descargar comprobante', $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment',
            'payment_id' => $this->payment->id,
            'appointment_id' => $this->payment->appointment_id,
            'message' => 'Se registró tu pago #'.$this->payment->id,
            'monto' => (float) $this->payment->monto,
            'propina' => (float) $this->payment->propina,
            'metodo_pago' => $this->payment->metodo_pago,
        ];
    }
}
