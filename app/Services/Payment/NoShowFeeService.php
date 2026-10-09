<?php

namespace App\Services\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\NoShowFee;
use App\Models\Payment;
use App\Services\Appointment\AppointmentNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Cargo por inasistencia (flujo de citas V2, etapa 2). Cuando el personal marca una cita como «no asistió»:
 *  1. Se calcula el cargo: `comision_no_show_porcentaje` (50 % por defecto, 0 lo desactiva) del servicio con el
 *     descuento que le tocaba al cliente. Lo que ya estaba pagado de forma anticipada (un depósito anti-no-show
 *     retenido) se descuenta para no cobrar dos veces.
 *  2. Si queda monto, se intenta cobrar solo a la tarjeta guardada del cliente.
 *  3. Si no se pudo (sin tarjeta guardada, rechazo, efectivo, transferencia), queda como adeudo: el cliente no puede
 *     reservar hasta que recepción lo cobre en sucursal o administración lo condone.
 *
 * Solo lo dispara una persona (barbero, recepción o administración) al marcar la inasistencia: el proceso automático
 * que marca citas vencidas no cobra, porque no sabe si el cliente fue atendido y el barbero olvidó registrarlo.
 */
class NoShowFeeService
{
    public const DEFAULT_PERCENT = 50;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly StripePaymentService $stripe,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /** Porcentaje vigente del cargo (0 = desactivado). */
    public function percent(): int
    {
        try {
            $value = BarbershopSetting::cached()?->getAttribute('comision_no_show_porcentaje');
        } catch (\Throwable) {
            $value = null;
        }

        return $value === null ? self::DEFAULT_PERCENT : max(0, min(100, (int) $value));
    }

    /** Genera (una sola vez por cita) el cargo de una cita marcada como «no asistió». */
    public function assess(Appointment $appointment): ?NoShowFee
    {
        if ($appointment->getAttribute('estado') !== 'no_asistio') {
            return null;
        }

        $existing = NoShowFee::query()->where('appointment_id', (string) $appointment->id)->first();
        if ($existing) {
            return $existing;
        }

        $percent = $this->percent();
        $base = round($this->payments->amountDueFor($appointment) * $percent / 100, 2);
        if ($percent <= 0 || $base <= 0) {
            return null;
        }

        $credit = min($base, $this->prepaidFor($appointment));
        $remaining = round($base - $credit, 2);

        try {
            $fee = NoShowFee::create([
                'appointment_id' => (string) $appointment->id,
                'client_id' => (string) $appointment->client_id,
                'monto' => $remaining,
                'monto_base' => $base,
                'credito_anticipo' => $credit,
                'porcentaje' => $percent,
                'estado' => $remaining <= 0 ? NoShowFee::ESTADO_PAGADO : NoShowFee::ESTADO_PENDIENTE,
            ] + ($remaining <= 0 ? ['metodo_cobro' => 'anticipo', 'cobrado_en' => now()] : []));
        } catch (BulkWriteException) {
            // Dos «no asistió» a la vez: el índice único deja pasar solo uno.
            return NoShowFee::query()->where('appointment_id', (string) $appointment->id)->first();
        }

        if ($fee->estado === NoShowFee::ESTADO_PENDIENTE) {
            $fee = $this->tryCard($fee, $appointment);
        }

        $this->notifier->noShowFee($appointment, $fee);

        return $fee;
    }

    /** Recepción/administración cobra el adeudo en sucursal (efectivo o transferencia). */
    public function markPaid(NoShowFee $fee, string $metodo, string $userId): NoShowFee
    {
        if ($fee->estado !== NoShowFee::ESTADO_PENDIENTE) {
            throw new PaymentException('Este cargo ya no está pendiente.');
        }

        if (! in_array($metodo, ['efectivo', 'transferencia'], true)) {
            throw new PaymentException('Método de cobro no válido para un cargo por inasistencia.');
        }

        $fee->update([
            'estado' => NoShowFee::ESTADO_PAGADO,
            'metodo_cobro' => $metodo,
            'cobrado_por' => $userId,
            'cobrado_en' => now(),
        ]);

        return $fee->fresh() ?? $fee;
    }

    /** Administración perdona el cargo (queda registrado quién y por qué). */
    public function waive(NoShowFee $fee, string $userId, string $motivo): NoShowFee
    {
        if ($fee->estado !== NoShowFee::ESTADO_PENDIENTE) {
            throw new PaymentException('Este cargo ya no está pendiente.');
        }

        $fee->update([
            'estado' => NoShowFee::ESTADO_CONDONADO,
            'motivo' => $motivo,
            'cobrado_por' => $userId,
            'cobrado_en' => now(),
        ]);

        return $fee->fresh() ?? $fee;
    }

    /** @return Collection<int, NoShowFee> */
    public function outstandingFor(string|Client $client): Collection
    {
        return NoShowFee::query()
            ->where('client_id', $client instanceof Client ? (string) $client->id : $client)
            ->where('estado', NoShowFee::ESTADO_PENDIENTE)
            ->get();
    }

    public function outstandingTotal(string|Client $client): float
    {
        return round((float) $this->outstandingFor($client)->sum(fn (NoShowFee $fee): float => (float) $fee->monto), 2);
    }

    private function tryCard(NoShowFee $fee, Appointment $appointment): NoShowFee
    {
        $client = $appointment->client;
        if (! $client instanceof Client) {
            return $fee;
        }

        try {
            $intentId = $this->stripe->chargeSavedCard($client, (float) $fee->monto, [
                'tipo' => 'cargo_inasistencia',
                'no_show_fee_id' => (string) $fee->id,
                'client_id' => (string) $client->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Fallo el cobro automático del cargo por inasistencia.', ['fee_id' => (string) $fee->id, 'error' => $e->getMessage()]);
            $intentId = null;
        }

        if ($intentId === null) {
            return $fee;
        }

        $fee->update([
            'estado' => NoShowFee::ESTADO_PAGADO,
            'metodo_cobro' => 'tarjeta',
            'stripe_payment_id' => $intentId,
            'cobrado_en' => now(),
        ]);

        return $fee->fresh() ?? $fee;
    }

    /** Pagado de forma anticipada y verificado (depósitos anti-no-show / pago completo al reservar). */
    private function prepaidFor(Appointment $appointment): float
    {
        return round((float) Payment::query()
            ->where('appointment_id', (string) $appointment->id)
            ->where('estado', Payment::ESTADO_VERIFICADO)
            ->where('es_deposito', true)
            ->get()
            ->sum(fn (Payment $payment): float => (float) $payment->monto), 2);
    }
}
