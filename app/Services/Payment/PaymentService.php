<?php

namespace App\Services\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Jobs\RunOcrOnComprobante;
use App\Models\Appointment;
use App\Models\Payment;
use App\Notifications\PaymentReceiptNotification;
use App\Notifications\TransferReceiptNotification;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\RaffleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Orquesta el cobro de citas: pagos directos por staff, subida y revision
 * de comprobantes de transferencia, y el "completar cobro" compartido
 * (marca cita completada, genera PDF de recibo y notifica al cliente).
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly AppointmentNotifier $notifier,
        private readonly LoyaltyService $loyalty,
        private readonly RaffleService $raffle,
    ) {}

    /**
     * Lista pagos paginados aplicando filtros del repositorio.
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->payments->paginateWithFilters($filters, $perPage);
    }

    /**
     * Cobro directo por staff: crea el pago ya verificado y completa el
     * cobro (cita completada + PDF + notificacion) dentro de una
     * transaccion DB para evitar estados intermedios inconsistentes.
     */
    public function create(array $payload, ?string $createdBy): Payment
    {
        $appointment = Appointment::query()->with(['client.user', 'barber.user', 'service'])->findOrFail($payload['appointment_id']);

        // Gate de cobro: solo citas aprobadas por el barbero (nunca pendiente).
        if (! in_array($appointment->estado, AppointmentStatusService::CHARGEABLE, true)) {
            throw new PaymentException('Solo se puede cobrar una cita aprobada por el barbero (confirmada, en proceso o completada). Esta cita esta en estado: '.$appointment->estado.'.');
        }

        if ($this->payments->existsForAppointment((string) $appointment->id)) {
            throw new PaymentException('La cita ya tiene un pago registrado.');
        }

        return DB::transaction(function () use ($payload, $createdBy, $appointment) {
            $client = $appointment->client;
            $puntosCanjeados = (int) ($payload['puntos_canjeados'] ?? 0);
            $usarPremioRifa = (bool) ($payload['usar_premio_rifa'] ?? false);

            if ($usarPremioRifa && $puntosCanjeados > 0) {
                throw new PaymentException('No puedes canjear puntos y usar el premio de la rifa en el mismo cobro.');
            }

            $premioRifa = null;

            if ($usarPremioRifa) {
                if (! $client) {
                    throw new PaymentException('No se puede aplicar el premio de la rifa: esta cita no tiene un cliente asociado.');
                }

                $premioRifa = $this->raffle->activePrizeFor($client);
                if (! $premioRifa) {
                    throw new PaymentException('Este cliente no tiene un premio de rifa disponible para reclamar.');
                }

                // El premio cubre el servicio completo: no se combina con el
                // descuento de nivel ni con puntos (ya validado arriba).
                $monto = 0.0;
            } else {
                // El precio base NUNCA se toma de $payload['monto'] (viene de un
                // formulario web, no hay que confiar en el en un cobro real) —
                // siempre se relee del servicio de la cita, igual que ya hacen
                // uploadTransferReceipt() y el intent de Stripe. El campo "Monto
                // del Servicio" del formulario es solo informativo/legado.
                $precioBase = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
                $monto = LoyaltyService::applyDiscount($precioBase, $client?->nivel ?? 'nuevo');

                if ($puntosCanjeados > 0) {
                    if (! $client) {
                        throw new PaymentException('No se pueden canjear puntos: esta cita no tiene un cliente asociado.');
                    }

                    $maxCanjeable = LoyaltyService::maxRedeemablePoints($monto, (int) $client->puntos);
                    if ($puntosCanjeados > $maxCanjeable) {
                        throw new PaymentException("Solo se pueden canjear hasta {$maxCanjeable} puntos en este cobro (tope: 50% del total o el saldo disponible del cliente).");
                    }

                    if (! $this->loyalty->redeemPoints($client, $puntosCanjeados, 'Canje aplicado al cobro de la cita '.$appointment->id)) {
                        throw new PaymentException('No se pudieron canjear los puntos indicados.');
                    }

                    // 1 punto = $1 MXN, ya validado contra el tope de arriba.
                    $monto -= $puntosCanjeados;
                }
            }

            $payment = $this->payments->create([
                'appointment_id' => $payload['appointment_id'],
                'monto' => $monto,
                'metodo_pago' => $payload['metodo_pago'],
                'propina' => $payload['propina'] ?? 0,
                'created_by' => $createdBy,
                'estado' => Payment::ESTADO_VERIFICADO,
                'puntos_canjeados' => $puntosCanjeados,
                'raffle_result_id' => $premioRifa?->id,
                'stripe_payment_id' => $payload['stripe_payment_id'] ?? null,
            ]);

            if ($premioRifa) {
                $this->raffle->claim($premioRifa, $appointment);
            }

            return $this->completeCharge($payment, $appointment, $monto);
        });
    }

    /**
     * El cliente sube su comprobante de transferencia. Queda en revision:
     * NO marca la cita como completada ni genera factura todavia. Avisa a
     * recepcion/admin para que lo revisen.
     */
    public function uploadTransferReceipt(Appointment $appointment, UploadedFile $file, string $clientUserId): Payment
    {
        if (! in_array($appointment->estado, AppointmentStatusService::CHARGEABLE, true)) {
            throw new PaymentException('Solo se puede subir comprobante de una cita aprobada por el barbero.');
        }

        if ($this->payments->existsForAppointment((string) $appointment->id)) {
            throw new PaymentException('La cita ya tiene un pago registrado o en revision.');
        }

        $path = $file->store('comprobantes-transferencia', 'public');

        // Mismo calculo que ClientPaymentController::create() ya le mostro al
        // cliente antes de que transfiriera, para que el monto registrado
        // coincida exactamente con lo que se le pidio transferir.
        $precioBase = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
        $monto = LoyaltyService::applyDiscount($precioBase, $appointment->client?->nivel ?? 'nuevo');

        $payment = $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => $monto,
            'metodo_pago' => 'transferencia',
            'propina' => 0,
            'created_by' => $clientUserId,
            'estado' => Payment::ESTADO_PENDIENTE_VERIFICACION,
            'comprobante_cliente' => $path,
        ]);

        $appointment->loadMissing(['client.user', 'service']);

        // Encola un job asincrono para leer el comprobante via OCR (ayuda a
        // recepcion/admin a verificarlo, no bloquea la subida).
        RunOcrOnComprobante::dispatch((string) $payment->id);

        // Avisa a recepcion/admin (canal interno) que hay un comprobante por revisar.
        $this->notifier->transferReceiptUploaded($appointment, $payment);

        if ($user = $appointment->client?->user) {
            try {
                $user->notify(new TransferReceiptNotification($payment, 'recibido'));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de comprobante recibido', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }

    /**
     * Aprueba un comprobante en revision y completa el cobro. Actualiza
     * estado dentro de transaccion DB y luego reusa completeCharge() para
     * marcar la cita, generar el PDF y notificar.
     */
    public function approveTransfer(Payment $payment, string $reviewerId): Payment
    {
        if ($payment->estado !== Payment::ESTADO_PENDIENTE_VERIFICACION) {
            throw new PaymentException('Este comprobante ya fue revisado.');
        }

        $appointment = Appointment::query()->with(['client.user', 'barber.user', 'service'])->findOrFail($payment->appointment_id);

        return DB::transaction(function () use ($payment, $appointment, $reviewerId) {
            $this->payments->update($payment->id, [
                'estado' => Payment::ESTADO_VERIFICADO,
                'revisado_por' => $reviewerId,
                'revisado_en' => now(),
            ]);

            $payment = $payment->fresh();

            return $this->completeCharge($payment, $appointment, (float) $payment->monto);
        });
    }

    /**
     * Rechaza un comprobante en revision (no completa el cobro) y notifica
     * al cliente el motivo para que pueda volver a subir un comprobante.
     */
    public function rejectTransfer(Payment $payment, string $reviewerId, string $motivo): Payment
    {
        if ($payment->estado !== Payment::ESTADO_PENDIENTE_VERIFICACION) {
            throw new PaymentException('Este comprobante ya fue revisado.');
        }

        $this->payments->update($payment->id, [
            'estado' => Payment::ESTADO_RECHAZADO,
            'revisado_por' => $reviewerId,
            'revisado_en' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        $payment = $payment->fresh(['appointment.client.user']);

        if ($user = $payment->appointment?->client?->user) {
            try {
                $user->notify(new TransferReceiptNotification($payment, 'rechazado', $motivo));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de comprobante rechazado', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }

    /**
     * Logica compartida de "completar el cobro": marca la cita completada,
     * genera la factura PDF y notifica al cliente. Usada tanto por el flujo
     * directo de staff (create) como por la aprobacion de transferencia.
     */
    private function completeCharge(Payment $payment, Appointment $appointment, float $monto): Payment
    {
        // 'completada' ya es un estado cobrable (CHARGEABLE incluye
        // 'completada' para permitir cobrar una cita que el barbero ya
        // marco como terminada) — hay que capturar el estado ANTES de
        // actualizarlo para no otorgar puntos de lealtad dos veces si la
        // cita ya habia sido completada por otra via (agenda del barbero o
        // el dropdown de estado de recepcion/admin).
        $wasCompletada = $appointment->estado === 'completada';

        // Efecto secundario: transiciona la cita a "completada" (fin del
        // flujo de estados) y fija el precio realmente cobrado.
        $appointment->update([
            'estado' => 'completada',
            'precio_cobrado' => $monto,
        ]);

        // Otorga puntos de lealtad la primera vez que la cita se completa.
        // Antes solo pasaba si se completaba desde el dropdown de estado o
        // la agenda del barbero; el flujo de cobro (el mas comun en la
        // practica) nunca lo disparaba.
        if (! $wasCompletada) {
            $client = $appointment->client;
            if ($client) {
                $this->loyalty->awardCitaPoints($client, (string) $appointment->id);
            }
        }

        // Genera el PDF del recibo/factura con DomPDF a partir de una vista Blade.
        $pdf = Pdf::loadView('payments.receipt', [
            'payment' => $payment->load(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator']),
        ]);

        // Persiste el PDF en el disco publico para poder servirlo despues.
        $pdfPath = 'comprobantes/pago-'.$payment->id.'.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $this->payments->update($payment->id, ['comprobante_pdf' => $pdfPath]);

        $payment = $payment->fresh(['appointment.client.user']);

        $user = $payment->appointment?->client?->user;

        // Notifica al cliente el recibo de pago; el fallo de notificacion
        // se registra pero no revierte el cobro ya completado.
        if ($user) {
            try {
                $user->notify(new PaymentReceiptNotification($payment));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificación comprobante de pago', [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }
}
