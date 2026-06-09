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
        // Use the client-accessible download route so the client can open it directly.
        $url = $this->payment->id
            ? route('client.facturas.download', $this->payment)
            : route('client.facturas.index');

        $appt    = $this->payment->appointment;
        $service = $appt?->service?->nombre ?? 'Servicio';
        $fecha   = optional($appt?->fecha)->format('d/m/Y') ?? '';

        return (new MailMessage)
            ->subject('Tu comprobante de pago — '.$service)
            ->greeting('Hola '.$notifiable->name.',')
            ->line('Tu pago ha sido registrado correctamente. Gracias por tu visita.')
            ->line('**Servicio:** '.$service.($fecha ? ' · '.$fecha : ''))
            ->line('**Monto:** $'.number_format((float) $this->payment->monto, 2))
            ->when((float) $this->payment->propina > 0, fn($m) =>
                $m->line('**Propina:** $'.number_format((float) $this->payment->propina, 2))
            )
            ->line('**Método de pago:** '.ucfirst($this->payment->metodo_pago ?? '—'))
            ->action('Ver mis facturas', $url);
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
