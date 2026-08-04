<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Lee el comprobante subido por el cliente con Tesseract (local, sin API de
 * pago) y guarda el texto crudo + un monto sugerido por regex. Puramente
 * informativo: ayuda visual para quien revisa, nunca aprueba nada solo.
 */
class RunOcrOnComprobante implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::find($this->paymentId);

        if (! $payment || ! $payment->comprobante_cliente) {
            return;
        }

        // Tesseract solo lee imagenes; los PDF se quedan sin sugerencia OCR.
        if (str_ends_with($payment->comprobante_cliente, '.pdf')) {
            return;
        }

        $absolutePath = Storage::disk('public')->path($payment->comprobante_cliente);

        try {
            $texto = (new TesseractOCR($absolutePath))->lang('spa', 'eng')->run();
        } catch (\Throwable $e) {
            Log::warning('Fallo OCR de comprobante', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $monto = $this->extractAmount($texto);

        $updates = ['ocr_texto' => $texto];
        // El cast decimal:2 no admite null al escribir: solo se incluye si hay valor.
        if ($monto !== null) {
            $updates['ocr_monto_detectado'] = $monto;
        }

        $payment->update($updates);
    }

    /**
     * Extrae el numero mas parecido a un monto en pesos (ej. "$1,234.56" o
     * "1234.56") del texto OCR. Puramente heuristico: toma el mayor numero
     * con formato de moneda encontrado.
     */
    private function extractAmount(string $texto): ?float
    {
        // Prioridad 1: numero precedido por $ con centavos explicitos, ej. "$1,234.56".
        if (preg_match_all('/\$\s?(\d{1,3}(?:,\d{3})*\.\d{2})/', $texto, $matches)) {
            $valores = array_map(fn ($v) => (float) str_replace(',', '', $v), $matches[1]);

            return max($valores);
        }

        // Prioridad 2: cualquier numero con formato de centavos (X.XX), sin signo de moneda.
        if (preg_match_all('/\b(\d{1,3}(?:,\d{3})*\.\d{2})\b/', $texto, $matches)) {
            $valores = array_map(fn ($v) => (float) str_replace(',', '', $v), $matches[1]);

            return max($valores);
        }

        return null;
    }
}
