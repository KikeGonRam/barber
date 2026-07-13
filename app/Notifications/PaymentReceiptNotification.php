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
        $monto   = (float) $this->payment->monto;
        $propina = (float) $this->payment->propina;

        $rows = [$service.($fecha ? ' · '.$fecha : '') => '$'.number_format($monto, 2)];
        if ($propina > 0) {
            $rows['Propina'] = '$'.number_format($propina, 2);
        }
        $rows['Metodo de pago'] = ucfirst($this->payment->metodo_pago ?? '—');

        return (new MailMessage)
            ->subject('Tu comprobante de pago — '.$service)
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Pagado',
                'title' => 'Gracias por tu visita',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Tu pago fue registrado correctamente. Aqui esta tu comprobante.',
                'rows' => $rows,
                'total' => ['label' => 'Total', 'value' => '$'.number_format($monto + $propina, 2)],
                'ctaLabel' => 'Ver mis facturas',
                'ctaUrl' => $url,
            ]);
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
