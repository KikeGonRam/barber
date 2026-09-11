<?php

namespace App\Services\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Jobs\RunOcrOnComprobante;
use App\Models\Appointment;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Notifications\Payment\TransferReceiptNotification;
use App\Services\Appointment\AppointmentNotifier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Política anti-no-show (roadmap P1): a un cliente con historial reciente de
 * inasistencias se le exige un depósito para poder reservar de nuevo. Vive
 * separado de PaymentService a propósito -- el depósito se cobra mientras la
 * cita sigue 'pendiente' (antes de que el barbero la apruebe), justo el
 * único momento en que PaymentService::create()/uploadTransferReceipt() se
 * niegan a operar (exigen CHARGEABLE). Comparte la colección `payments` con
 * el cobro normal (mismo corte de caja, misma revisión de transferencias),
 * distinguido por el campo `es_deposito`.
 */
class DepositService
{
    public function __construct(
        private readonly StripePaymentService $stripe,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Cuenta las inasistencias del cliente en los últimos 90 días y decide
     * si su siguiente reserva exige depósito. El umbral/porcentaje son
     * configurables por el negocio (BarbershopSetting); 0 desactiva la
     * política sin tener que tocar código.
     */
    public function requirementFor(?Client $client, Service $service): array
    {
        $settings = BarbershopSetting::cached();
        $umbral = (int) ($settings?->deposito_no_show_umbral ?? 2);
        $porcentaje = (int) ($settings?->deposito_no_show_porcentaje ?? 50);

        if (! $client || $umbral <= 0 || $porcentaje <= 0) {
            return ['requerido' => false, 'monto' => 0.0];
        }

        $inasistencias = Appointment::where('client_id', (string) $client->id)
            ->where('estado', 'no_asistio')
            ->where('fecha', '>=', now()->subDays(90))
            ->count();

        if ($inasistencias < $umbral) {
            return ['requerido' => false, 'monto' => 0.0];
        }

        $monto = round(((float) $service->precio) * $porcentaje / 100, 2);

        return ['requerido' => true, 'monto' => $monto];
    }

    /**
     * Depósito ya verificado de esta cita (si existe), para que
     * PaymentService::create() lo reste del cobro final. Nunca hay más de
     * uno activo (índice único compuesto de la migración).
     */
    public function verifiedAmountFor(Appointment $appointment): float
    {
        $deposito = $appointment->deposits()->where('estado', Payment::ESTADO_VERIFICADO)->first();

        return $deposito ? (float) $deposito->monto : 0.0;
    }

    private function guardCanCharge(Appointment $appointment): void
    {
        if (! $appointment->deposito_requerido) {
            throw new PaymentException('Esta cita no requiere depósito.');
        }

        if ($appointment->estado !== 'pendiente') {
            throw new PaymentException('El depósito solo se cobra mientras la cita está pendiente de aprobación.');
        }

        if ($appointment->deposits()->where('estado', '!=', Payment::ESTADO_RECHAZADO)->exists()) {
            throw new PaymentException('Esta cita ya tiene un depósito registrado o en revisión.');
        }
    }

    /**
     * Crea el PaymentIntent de Stripe para el depósito. El monto nunca sale
     * del payload del cliente: se relee de appointment.deposito_monto,
     * fijado por AppointmentController::store() al crear la cita.
     */
    public function createStripeIntent(Appointment $appointment): array
    {
        $this->guardCanCharge($appointment);

        return $this->stripe->createPaymentIntent(
            (float) $appointment->deposito_monto,
            'mxn',
            [
                'appointment_id' => (string) $appointment->id,
                'es_deposito' => 'true',
            ]
        );
    }

    /**
     * Registra el depósito cobrado por Stripe (llamado desde el webhook).
     * Idempotente: si el webhook llega dos veces, el índice único compuesto
     * rechaza el duplicado y aquí se traduce en un no-op silencioso, igual
     * que PaymentService::create() ante un reintento de webhook.
     */
    public function confirmStripeDeposit(Appointment $appointment, string $stripePaymentIntentId, float $monto): void
    {
        try {
            Payment::create([
                'appointment_id' => (string) $appointment->id,
                'monto' => $monto,
                'metodo_pago' => 'tarjeta',
                'propina' => 0,
                'estado' => Payment::ESTADO_VERIFICADO,
                'es_deposito' => true,
                'stripe_payment_id' => $stripePaymentIntentId,
            ]);
        } catch (BulkWriteException $e) {
            Log::info('Stripe webhook: deposito para cita '.$appointment->id.' ya estaba registrado, se omite', [
                'payment_intent_id' => $stripePaymentIntentId,
            ]);
        }
    }

    /**
     * El cliente sube su comprobante de transferencia del depósito. Queda en
     * revisión, igual que un comprobante de cobro normal.
     */
    public function uploadTransferReceipt(Appointment $appointment, UploadedFile $file, string $clientUserId): Payment
    {
        $this->guardCanCharge($appointment);

        $path = $file->store('comprobantes-transferencia', 'public');

        try {
            $payment = Payment::create([
                'appointment_id' => (string) $appointment->id,
                'monto' => (float) $appointment->deposito_monto,
                'metodo_pago' => 'transferencia',
                'propina' => 0,
                'created_by' => $clientUserId,
                'estado' => Payment::ESTADO_PENDIENTE_VERIFICACION,
                'comprobante_cliente' => $path,
                'es_deposito' => true,
            ]);
        } catch (BulkWriteException $e) {
            throw new PaymentException('Esta cita ya tiene un depósito registrado o en revisión.');
        }

        RunOcrOnComprobante::dispatch((string) $payment->id);

        $appointment->loadMissing('client.user');
        if ($user = $appointment->client?->user) {
            try {
                $user->notify(new TransferReceiptNotification($payment, 'recibido'));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de comprobante de deposito recibido', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }

    /**
     * Aprueba un depósito por transferencia. A diferencia de
     * PaymentService::approveTransfer(), NO completa la cita ni otorga
     * puntos -- el depósito no es el cobro del servicio, solo lo antecede.
     */
    public function approve(Payment $payment, string $reviewerId): Payment
    {
        if (! $payment->es_deposito) {
            throw new PaymentException('Este pago no es un depósito.');
        }

        if ($payment->estado !== Payment::ESTADO_PENDIENTE_VERIFICACION) {
            throw new PaymentException('Este comprobante ya fue revisado.');
        }

        $payment->update([
            'estado' => Payment::ESTADO_VERIFICADO,
            'revisado_por' => $reviewerId,
            'revisado_en' => now(),
        ]);

        $payment = $payment->fresh(['appointment.client.user']);

        if ($user = $payment->appointment?->client?->user) {
            try {
                $user->notify(new TransferReceiptNotification($payment, 'recibido'));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de deposito aprobado', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return $payment;
    }

    /**
     * Rechaza un depósito por transferencia; el índice único compuesto
     * (bloquea_cita pasa a false) libera el hueco para que el cliente pueda
     * volver a subir un comprobante.
     */
    public function reject(Payment $payment, string $reviewerId, string $motivo): Payment
    {
        if (! $payment->es_deposito) {
            throw new PaymentException('Este pago no es un depósito.');
        }

        if ($payment->estado !== Payment::ESTADO_PENDIENTE_VERIFICACION) {
            throw new PaymentException('Este comprobante ya fue revisado.');
        }

        $payment->update([
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
                Log::warning('Fallo notificacion de deposito rechazado', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return $payment;
    }

    /**
     * Al cancelar una cita DENTRO de la política de cancelación (nunca por
     * no-show: eso pasa por otro camino y no llama este método), devuelve
     * el depósito verificado si existe. Tarjeta -> reembolso real en
     * Stripe (el webhook charge.refunded es quien marca ESTADO_REEMBOLSADO,
     * igual que el resto del flujo de reembolsos ya existente). Transferencia
     * -> no hay nada que revertir electrónicamente, se marca de una vez
     * (la devolución física la gestiona el staff).
     */
    public function refundIfAny(Appointment $appointment): void
    {
        $deposito = $appointment->deposits()->where('estado', Payment::ESTADO_VERIFICADO)->first();

        if (! $deposito) {
            return;
        }

        if ($deposito->metodo_pago === 'tarjeta' && $deposito->stripe_payment_id) {
            try {
                $this->stripe->refund($deposito->stripe_payment_id);
            } catch (\Throwable $e) {
                Log::warning('No se pudo reembolsar el deposito por Stripe', [
                    'payment_id' => $deposito->id,
                    'error' => $e->getMessage(),
                ]);
                $this->notifier->sendStaff(
                    $appointment,
                    'Reembolso de depósito fallido',
                    'No se pudo reembolsar un depósito automáticamente',
                    'El depósito de esta cita cancelada no se pudo reembolsar por Stripe automáticamente. Revísalo manualmente en el dashboard de Stripe.',
                    '#ef4444',
                    'Reembolso fallido',
                );
            }

            return;
        }

        DB::transaction(function () use ($deposito) {
            $deposito->update(['estado' => Payment::ESTADO_REEMBOLSADO]);
        });
    }
}
