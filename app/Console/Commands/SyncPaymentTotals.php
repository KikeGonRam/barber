<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

/**
 * Backfill de 'monto_total' (monto + propina) para pagos creados antes de que
 * Payment::booted() empezara a mantener ese campo automaticamente en cada
 * guardado. Sin esto, los pagos viejos ordenarian mal en la columna "Total"
 * del listado (payments/index.blade.php) hasta que se editen una vez.
 * Se ejecuta a mano; no está en el scheduler.
 */
class SyncPaymentTotals extends Command
{
    protected $signature = 'payments:sync-totals {--dry-run : Solo muestra cuántos pagos cambiarían, sin escribir}';

    protected $description = 'Recalcula monto_total (monto + propina) en los pagos existentes que no lo tengan actualizado';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $payments = Payment::all(['_id', 'monto', 'propina', 'monto_total']);
        $this->info("Pagos a revisar: {$payments->count()}");

        $updated = 0;

        foreach ($payments as $payment) {
            $expected = round((float) $payment->monto + (float) $payment->propina, 2);
            $current = $payment->getRawOriginal('monto_total');

            if ($current !== null && round((float) $current, 2) === $expected) {
                continue;
            }

            $updated++;

            if (! $dryRun) {
                // save() dispara Payment::booted()'s saving hook, que recalcula monto_total.
                $payment->save();
            }
        }

        $this->info($dryRun
            ? "{$updated} pago(s) tienen monto_total desincronizado/ausente (dry-run, nada escrito)."
            : "{$updated} pago(s) actualizados.");

        return self::SUCCESS;
    }
}
