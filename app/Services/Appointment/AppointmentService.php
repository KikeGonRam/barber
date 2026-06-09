<?php

namespace App\Services\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Service;
use App\Notifications\AppointmentNotification;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Carbon\Carbon;

class AppointmentService
{
    public function __construct(private readonly AppointmentRepositoryInterface $appointments) {}

    public function getAvailableSlots(Barber $barber, string $date, Service $service): array
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = $carbonDate->dayOfWeek; // 0 (Sun) to 6 (Sat)

        $barberSchedule = $barber->schedules()->where('day_of_week', $dayOfWeek)->first();

        $startTime = null;
        $endTime = null;
        $isWorking = false;

        if ($barberSchedule) {
            $startTime = $barberSchedule->start_time;
            $endTime = $barberSchedule->end_time;
            $isWorking = $barberSchedule->is_working;
        } else {
            // FALLBACK: Si no hay horario de barbero, usar horario global
            $settings = BarbershopSetting::first();
            if ($settings && $settings->horario_apertura && $settings->horario_cierre) {
                $startTime = $settings->horario_apertura;
                $endTime = $settings->horario_cierre;
                // Por defecto, permitir lunes a sábado si no hay configuración específica
                $isWorking = ($dayOfWeek !== 0);
            }
        }

        // Si el barbero explícitamente no trabaja o faltan datos, lista vacía
        if (! $isWorking || ! $startTime || ! $endTime) {
            return [];
        }

        $start = Carbon::parse($date.' '.$startTime)->startOfMinute();
        $end = Carbon::parse($date.' '.$endTime)->startOfMinute();
        $duration = (int) ($service->duracion_min ?? 30);
        $interval = 30;

        $slots = [];
        $current = $start->copy();

        // Citas existentes para este barbero
        $existing = Appointment::where('barber_id', (string) $barber->id)
            ->whereDate('fecha', $date)
            ->where('estado', '!=', 'cancelada')
            ->get(['hora_inicio', 'hora_fin']);

        while ($current->copy()->addMinutes($duration)->lte($end)) {
            $slotStart = $current->format('H:i:00');
            $slotEnd = $current->copy()->addMinutes($duration)->format('H:i:00');

            $isAvailable = true;

            // Si es HOY, solo permitir horarios en el futuro (margen de 10 min)
            if ($carbonDate->isToday() && $current->lt(now()->addMinutes(10))) {
                $isAvailable = false;
            }

            if ($isAvailable) {
                foreach ($existing as $appt) {
                    $aS = Carbon::parse($appt->hora_inicio)->format('H:i:00');
                    $aE = Carbon::parse($appt->hora_fin)->format('H:i:00');

                    if (($slotStart >= $aS && $slotStart < $aE) || ($slotEnd > $aS && $slotEnd <= $aE) || ($slotStart <= $aS && $slotEnd >= $aE)) {
                        $isAvailable = false;
                        break;
                    }
                }
            }

            if ($isAvailable) {
                $slots[] = [
                    'time'      => $current->format('H:i'),
                    'label'     => $current->format('g:i A'),
                    'end_time'  => $current->copy()->addMinutes($duration)->format('H:i'),
                    'end_label' => $current->copy()->addMinutes($duration)->format('g:i A'),
                ];
            }

            $current->addMinutes($interval);
        }

        return $slots;
    }

    public function createAppointment(array $payload)
    {
        $this->ensureNoOverlap($payload);

        $appointment = $this->appointments->create($payload);
        $appointment->load(['client.user', 'service']);

        $user = $appointment->client?->user;

        if ($user) {
            try {
                $user->notify(new AppointmentNotification(
                    appointment: $appointment,
                    subject: 'Confirmación de cita',
                    title: 'Tu cita fue registrada',
                    message: 'Tu cita fue confirmada. Te esperamos el día indicado.',
                ));

                $appointment->update(['confirmation_sent_at' => now()]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Fallo notificación confirmación de cita', [
                    'appointment_id' => $appointment->id,
                    'user_id'        => $user->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        return $appointment;
    }

    public function updateAppointment(string $appointmentId, array $payload): bool
    {
        $this->ensureNoOverlap($payload, $appointmentId);

        return $this->appointments->update($appointmentId, $payload);
    }

    private function ensureNoOverlap(array $payload, ?string $ignoreAppointmentId = null): void
    {
        $start = Carbon::parse($payload['hora_inicio'])->format('H:i:00');
        $end = Carbon::parse($payload['hora_fin'])->format('H:i:00');

        $hasOverlap = $this->appointments->hasOverlap(
            barberId: (string) $payload['barber_id'],
            date: (string) $payload['fecha'],
            startTime: $start,
            endTime: $end,
            ignoreAppointmentId: $ignoreAppointmentId,
        );

        if ($hasOverlap) {
            throw new AppointmentConflictException('El barbero ya tiene una cita en este rango de tiempo.');
        }
    }
}
