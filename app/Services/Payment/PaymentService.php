<?php

namespace App\Services\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Notifications\PaymentReceiptNotification;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Appointment\AppointmentStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    public function __construct(private readonly PaymentRepositoryInterface $payments) {}

    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->payments->paginateWithFilters($filters, $perPage);
    }

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
            ]);

            $appointment->update([
                'estado' => 'completada',
                'precio_cobrado' => $payload['monto'],
            ]);

            $pdf = Pdf::loadView('payments.receipt', [
                'payment' => $payment->load(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator']),
            ]);

            $pdfPath = 'comprobantes/pago-'.$payment->id.'.pdf';
            Storage::disk('public')->put($pdfPath, $pdf->output());

            $this->payments->update($payment->id, ['comprobante_pdf' => $pdfPath]);

            $payment = $payment->fresh(['appointment.client.user']);

            $user = $payment->appointment?->client?->user;

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
        });
    }
}
