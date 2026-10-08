<?php

namespace App\Notifications\Membership;

use App\Models\MembershipInvoice;
use App\Notifications\Concerns\PushesToDevices;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Factura de cada cobro mensual de la membresía (alta y renovaciones). Se manda al registrar el cobro
 * (MembershipService::recordSuccessfulInvoice), una sola vez por factura de Stripe, con la factura en PDF adjunta.
 */
class MembershipInvoiceNotification extends Notification implements ShouldQueue
{
    use PushesToDevices;
    use Queueable;

    public function __construct(public readonly MembershipInvoice $invoice, public readonly string $planName) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('in_app')) {
            $channels[] = 'database';
        }

        if (method_exists($notifiable, 'wantsNotificationChannel') && $notifiable->wantsNotificationChannel('email')) {
            $channels[] = 'mail';
        }

        return [...($channels ?: ['database']), ...$this->pushChannels($notifiable)];
    }

    private function folio(): string
    {
        return 'M-'.strtoupper(substr((string) ($this->invoice->getAttribute('stripe_invoice_id') ?: $this->invoice->id), -6));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $monto = (float) $this->invoice->getAttribute('monto');
        $pagado = optional($this->invoice->getAttribute('pagado_en'))->format('d/m/Y') ?? now()->format('d/m/Y');

        $mail = (new MailMessage)
            ->subject('Tu factura de la membresía '.$this->planName)
            ->markdown('emails.message', [
                'accent' => '#d4af37',
                'badge' => 'Pagado',
                'title' => 'Pago de tu membresía',
                'greeting' => 'Hola '.$notifiable->name.',',
                'intro' => 'Recibimos el pago mensual de tu membresía. Adjuntamos tu factura en PDF.',
                'rows' => ['Membresía '.$this->planName.' · '.$pagado => '$'.number_format($monto, 2)],
                'total' => ['label' => 'Total', 'value' => '$'.number_format($monto, 2)],
                'ctaLabel' => 'Ver mis facturas',
                'ctaUrl' => config('app.frontend_url').'/my/invoices',
            ]);

        // Factura en PDF (no crítico: si falla, el correo igual sale).
        try {
            $pdf = Pdf::loadView('pdf.invoice', [
                'folio' => $this->folio(),
                'emitido' => now()->format('d/m/Y'),
                'cliente' => $notifiable->name,
                'servicio' => 'Membresía '.$this->planName,
                'fecha' => $pagado,
                'barbero' => null,
                'monto' => $monto,
                'propina' => 0.0,
                'metodo' => 'Tarjeta',
            ])->output();

            $mail->attachData($pdf, 'factura-'.$this->folio().'.pdf', ['mime' => 'application/pdf']);
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar la factura PDF de la membresía', ['invoice_id' => $this->invoice->id, 'error' => $e->getMessage()]);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'membership_invoice',
            'invoice_id' => $this->invoice->id,
            'title' => 'Pago de membresía',
            'message' => 'Recibimos tu pago mensual de $'.number_format((float) $this->invoice->getAttribute('monto'), 2).' MXN de la membresía '.$this->planName.'. Ya puedes ver tu factura.',
            'monto' => (float) $this->invoice->getAttribute('monto'),
            'url' => config('app.frontend_url').'/my/invoices',
        ];
    }
}
