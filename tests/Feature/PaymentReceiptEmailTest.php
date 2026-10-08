<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Notifications\Payment\PaymentReceiptNotification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El correo del pago lleva el comprobante y la factura juntos. */
class PaymentReceiptEmailTest extends TestCase
{
    private function notifiable(): object
    {
        return new class
        {
            public string $name = 'Cliente';
        };
    }

    /** @return list<string> nombres de los adjuntos del correo */
    private function attachmentNames(Payment $payment): array
    {
        $mail = (new PaymentReceiptNotification($payment))->toMail($this->notifiable());

        return array_map(fn (array $a): string => $a['name'], $mail->rawAttachments);
    }

    public function test_el_correo_adjunta_comprobante_y_factura(): void
    {
        Storage::fake('receipts');
        Storage::disk('receipts')->put('comprobantes/pago-abc123.pdf', '%PDF-comprobante');
        $payment = new Payment(['monto' => 250, 'propina' => 0, 'metodo_pago' => 'tarjeta', 'comprobante_pdf' => 'comprobantes/pago-abc123.pdf']);
        $payment->id = 'abcdef123456';

        $names = $this->attachmentNames($payment);

        $this->assertCount(2, $names);
        $this->assertContains('comprobante-F-123456.pdf', $names);
        $this->assertContains('factura-F-123456.pdf', $names);
    }

    public function test_si_no_hay_comprobante_el_correo_sale_solo_con_la_factura(): void
    {
        Storage::fake('receipts');
        $payment = new Payment(['monto' => 250, 'propina' => 0, 'metodo_pago' => 'efectivo']);
        $payment->id = 'abcdef123456';

        $this->assertSame(['factura-F-123456.pdf'], $this->attachmentNames($payment));
    }
}
