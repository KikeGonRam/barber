<?php

namespace App\Notifications\Payment;

use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Comprobante de pago (factura) enviado al cliente. Se dispara desde
 * PaymentService al registrar un pago exitoso (efectivo/tarjeta o
 * transferencia ya aprobada).
 */
class PaymentReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    /**
     * Igual que AppointmentNotification: sin preferencias explicitas cae a
     * 'database' para no perder el aviso.
     */
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

    /**
     * Arma el correo de comprobante y adjunta la factura en PDF generada
     * en el momento (ver bloque try/catch abajo).
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Manda a la lista de facturas del cliente en Nuxt (no hay ruta de
        // descarga directa por id ahí — el botón de descarga en esa página
        // llama a la API bajo demanda, ver PaymentController::receipt()).
        $url = config('app.frontend_url').'/my/invoices';

        $appt = $this->payment->appointment;
        $service = $appt?->service?->nombre ?? 'Servicio';
        $fecha = optional($appt?->fecha)->format('d/m/Y') ?? '';
        $monto = (float) $this->payment->monto;
        $propina = (float) $this->payment->propina;

        $rows = [$service.($fecha ? ' · '.$fecha : '') => '$'.number_format($monto, 2)];
        if ($propina > 0) {
            $rows['Propina'] = '$'.number_format($propina, 2);
        }
        $rows['Metodo de pago'] = ucfirst($this->payment->metodo_pago ?? '—');

        $mail = (new MailMessage)
            ->subject('Tu comprobante de pago — '.$service)
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Pagado',
                'title' => 'Gracias por tu visita',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Tu pago fue registrado correctamente. Adjuntamos tu factura en PDF.',
                'rows' => $rows,
                'total' => ['label' => 'Total', 'value' => '$'.number_format($monto + $propina, 2)],
                'ctaLabel' => 'Ver mis facturas',
                'ctaUrl' => $url,
            ]);

        // Adjunta la factura en PDF (no critico: si falla, el correo igual sale).
        try {
            $folio = 'F-'.strtoupper(substr((string) $this->payment->id, -6));
            $pdf = Pdf::loadView('pdf.invoice', [
                'folio' => $folio,
                'emitido' => now()->format('d/m/Y'),
                'cliente' => $notifiable->name,
                'servicio' => $service,
                'fecha' => $fecha,
                'barbero' => $appt?->barber?->user?->name,
                'monto' => $monto,
                'propina' => $propina,
                'metodo' => ucfirst($this->payment->metodo_pago ?? '—'),
            ])->output();

            $mail->attachData($pdf, 'factura-'.$folio.'.pdf', ['mime' => 'application/pdf']);
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar la factura PDF', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $mail;
    }

    /**
     * Payload para el canal database (centro de notificaciones in-app).
     */
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
