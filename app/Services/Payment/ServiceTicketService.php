<?php

namespace App\Services\Payment;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Appointment\ServiceStartGuard;
use App\Support\ReceiptStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Ticket del servicio (flujo de citas V2, etapa 4). Al terminar el servicio (cita `completada`):
 *  - se le envía al cliente el correo con su comprobante y su factura (cualquier método de pago: tarjeta, transferencia
 *    verificada o efectivo cobrado en recepción), si el cobro se registró antes de iniciar;
 *  - si pagó el servicio completo al reservar y nunca hubo un cobro final en recepción, se registra ahora ese cobro
 *    final (neto en $0, porque el depósito ya cubre todo) para que exista el comprobante y quede en el corte;
 *  - las apps muestran el ticket en pantalla (GET /appointments/{cita}/ticket y en la respuesta de «completada»).
 */
class ServiceTicketService
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ServiceStartGuard $guard,
    ) {}

    /** Emite el ticket de una cita ya completada. Nunca lanza: un fallo se deja en el log y la cita sigue completada. */
    public function issueOnCompletion(Appointment $appointment, ?string $actorId = null): ?Payment
    {
        if ($appointment->getAttribute('estado') !== 'completada') {
            return null;
        }

        try {
            $payment = $this->finalPayment($appointment);

            if (! $payment && $this->guard->isPaymentResolved($appointment)) {
                $payment = $this->payments->create([
                    'appointment_id' => (string) $appointment->id,
                    'monto' => 0,
                    'metodo_pago' => $this->prepaidMethod($appointment),
                    'propina' => 0,
                ], $actorId);
            }

            if ($payment) {
                $this->payments->sendTicket($payment);
            }

            return $payment;
        } catch (\Throwable $e) {
            Log::warning('No se pudo emitir el ticket del servicio.', [
                'appointment_id' => (string) $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Datos del ticket para mostrarlo en pantalla; null si la cita aún no está completada o no tiene cobro.
     *
     * @return array<string, mixed>|null
     */
    public function ticketFor(Appointment $appointment): ?array
    {
        if ($appointment->getAttribute('estado') !== 'completada') {
            return null;
        }

        $payment = $this->finalPayment($appointment);
        if (! $payment) {
            return null;
        }

        $appointment->loadMissing(['client.user', 'barber.user', 'service']);

        $price = (float) ($appointment->service?->getAttribute('precio') ?? 0);
        $monto = (float) $payment->monto;
        $propina = (float) $payment->propina;
        $deposit = $this->depositsPaid($appointment);

        return [
            'folio' => 'F-'.strtoupper(substr((string) $payment->id, -6)),
            'cita' => $appointment->getAttribute('code'),
            'fecha' => $appointment->getAttribute('fecha') ? Carbon::parse($appointment->getAttribute('fecha'))->toDateString() : null,
            'cliente' => data_get($appointment, 'client.user.name'),
            'barbero' => data_get($appointment, 'barber.user.name'),
            'servicio' => $appointment->service?->getAttribute('nombre'),
            'duracion_min' => (int) ($appointment->service?->getAttribute('duracion_min') ?? 0),
            'minutos_extra' => (int) $appointment->getAttribute('minutos_extra'),
            'metodo_pago' => $payment->metodo_pago,
            'precio_servicio' => $price,
            'descuentos' => max(0.0, round($price - $monto - $deposit, 2)),
            'deposito_aplicado' => $deposit,
            'monto' => $monto,
            'propina' => $propina,
            'total_pagado' => round($monto + $propina + $deposit, 2),
            'comprobante_url' => ReceiptStorage::url($payment->comprobante_pdf),
        ];
    }

    private function finalPayment(Appointment $appointment): ?Payment
    {
        return Payment::query()
            ->where('appointment_id', (string) $appointment->id)
            ->where('estado', Payment::ESTADO_VERIFICADO)
            ->where(fn ($q) => $q->where('es_deposito', false)->orWhereNull('es_deposito'))
            ->first();
    }

    private function depositsPaid(Appointment $appointment): float
    {
        return round((float) Payment::query()
            ->where('appointment_id', (string) $appointment->id)
            ->where('estado', Payment::ESTADO_VERIFICADO)
            ->where('es_deposito', true)
            ->get()
            ->sum(fn (Payment $payment): float => (float) $payment->monto), 2);
    }

    private function prepaidMethod(Appointment $appointment): string
    {
        $deposit = Payment::query()
            ->where('appointment_id', (string) $appointment->id)
            ->where('estado', Payment::ESTADO_VERIFICADO)
            ->where('es_deposito', true)
            ->first();

        return (string) ($deposit?->metodo_pago ?: 'tarjeta');
    }
}
