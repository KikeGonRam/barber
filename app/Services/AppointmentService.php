<?php

namespace App\Services;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Notifications\AppointmentNotification;
use App\Repositories\Contracts\AppointmentRepositoryInterface;

class AppointmentService
{
    public function __construct(private readonly AppointmentRepositoryInterface $appointments)
    {
    }

    public function createAppointment(array $payload)
    {
        $this->ensureNoOverlap($payload);

        $appointment = $this->appointments->create($payload);
        $appointment->load(['client.user', 'service']);

        $user = $appointment->client?->user;

        if ($user) {
            $user->notify(new AppointmentNotification(
                appointment: $appointment,
                subject: 'Confirmación de cita',
                title: 'Tu cita fue registrada',
                message: 'Tu cita fue confirmada en el sistema.',
            ));

            $appointment->update(['confirmation_sent_at' => now()]);
        }

        return $appointment;
    }

    public function updateAppointment(int $appointmentId, array $payload): bool
    {
        $this->ensureNoOverlap($payload, $appointmentId);

        return $this->appointments->update($appointmentId, $payload);
    }

    private function ensureNoOverlap(array $payload, ?int $ignoreAppointmentId = null): void
    {
        $hasOverlap = $this->appointments->hasOverlap(
            barberId: (int) $payload['barber_id'],
            date: (string) $payload['fecha'],
            startTime: (string) $payload['hora_inicio'],
            endTime: (string) $payload['hora_fin'],
            ignoreAppointmentId: $ignoreAppointmentId,
        );

        if ($hasOverlap) {
            throw new AppointmentConflictException('El barbero no tiene disponibilidad en el horario solicitado.');
        }
    }
}
