<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;

/**
 * Reglas para pasar una cita a «en proceso» (iniciar el servicio):
 *  1. Solo el día de la cita y desde unos minutos antes de su hora (config appointments.start_margin_minutes).
 *  2. El pago tiene que estar resuelto: cobro verificado (tarjeta, transferencia verificada o efectivo cobrado
 *     en recepción) o un pago completo hecho al reservar. Una transferencia en revisión o un depósito parcial
 *     anti-no-show NO cuentan.
 *
 * La cita y el pago son estados distintos: para iniciar deben cumplirse los dos (la cita ya aprobada por el barbero,
 * eso lo exige la máquina de estados).
 */
class ServiceStartGuard
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Motivo por el que no se puede iniciar, o null si se puede. */
    public function reasonCannotStart(Appointment $appointment, ?Carbon $now = null): ?string
    {
        $now ??= now();

        $timeReason = $this->timeReason($appointment, $now);
        if ($timeReason !== null) {
            return $timeReason;
        }

        if (! $this->isPaymentResolved($appointment)) {
            return 'El pago de esta cita aún no está resuelto. Cóbrala en recepción o espera a que se verifique la transferencia antes de iniciar el servicio.';
        }

        return null;
    }

    /** Pago resuelto: cobro final verificado o pago completo al reservar (depósito verificado que cubre el servicio). */
    public function isPaymentResolved(Appointment $appointment): bool
    {
        $appointmentId = (string) $appointment->id;

        $finalCharge = Payment::query()
            ->where('appointment_id', $appointmentId)
            ->where('estado', Payment::ESTADO_VERIFICADO)
            ->where(fn ($q) => $q->where('es_deposito', false)->orWhereNull('es_deposito'))
            ->exists();

        if ($finalCharge) {
            return true;
        }

        $due = $this->payments->amountDueFor($appointment);
        if ($due <= 0) {
            return false;
        }

        // monto viene como Decimal128 de Mongo: se suma con el cast del modelo, no con sum() del query.
        $prepaid = (float) Payment::query()
            ->where('appointment_id', $appointmentId)
            ->where('estado', Payment::ESTADO_VERIFICADO)
            ->where('es_deposito', true)
            ->get()
            ->sum(fn (Payment $payment): float => (float) $payment->monto);

        return $prepaid + 0.009 >= $due;
    }

    private function timeReason(Appointment $appointment, Carbon $now): ?string
    {
        $date = Carbon::parse($appointment->fecha)->startOfDay();
        $today = $now->copy()->startOfDay();

        if ($date->gt($today)) {
            return 'Esta cita es para el '.$date->translatedFormat('j \d\e F').': todavía no se puede iniciar.';
        }

        if ($date->lt($today)) {
            return 'Esta cita era del '.$date->translatedFormat('j \d\e F').': ya no se puede iniciar. Márcala como no asistió o reprográmala.';
        }

        $start = Carbon::parse($date->format('Y-m-d').' '.($appointment->hora_inicio ?: '00:00'));
        $margin = max(0, (int) config('appointments.start_margin_minutes', 15));
        $earliest = $start->copy()->subMinutes($margin);

        if ($now->lt($earliest)) {
            return 'Aún es pronto: la cita es a las '.$start->format('H:i').' y podrás iniciarla desde las '.$earliest->format('H:i').'.';
        }

        return null;
    }
}
