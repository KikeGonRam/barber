<?php

namespace App\Services\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Notifications\PaymentReceiptNotification;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentService
{
    public function __construct(private readonly PaymentRepositoryInterface $payments) {}

    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->payments->paginateWithFilters($filters, $perPage);
    }

    public function create(array $payload, int $createdBy): Payment
    {
        $appointment = Appointment::query()->with(['client.user', 'barber.user', 'service'])->findOrFail($payload['appointment_id']);

        if (in_array($appointment->estado, ['cancelada', 'no_asistio'], true)) {
            throw new PaymentException('No se puede registrar un pago para una cita cancelada o no asistida.');
        }

        if ($this->payments->existsForAppointment((int) $appointment->id)) {
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
                $user->notify(new PaymentReceiptNotification($payment));
            }

            return $payment;
        });
    }
}
