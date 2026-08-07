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
    public function create(array $payload, string $createdBy): Payment
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
            $payment = $this->payments->create([
                'appointment_id' => $payload['appointment_id'],
                'monto' => $payload['monto'],
                'metodo_pago' => $payload['metodo_pago'],
                'propina' => $payload['propina'] ?? 0,
                'created_by' => $createdBy,
                'estado' => Payment::ESTADO_VERIFICADO,
            ]);

            return $this->completeCharge($payment, $appointment, (float) $payload['monto']);
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

        $monto = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);

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
        // Efecto secundario: transiciona la cita a "completada" (fin del
        // flujo de estados) y fija el precio realmente cobrado.
        $appointment->update([
            'estado' => 'completada',
            'precio_cobrado' => $monto,
        ]);

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
