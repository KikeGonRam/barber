<?php

namespace App\Services\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\ClientAlreadyBookedException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Service;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Carbon\Carbon;

/**
 * Orquesta la creacion/edicion de citas: calcula disponibilidad de horarios
 * y valida que no haya conflictos (cliente con doble cita el mismo dia,
 * barbero con solape de horario) antes de persistir. Dispara notificaciones
 * tras crear la cita, pero no maneja el resto de la maquina de estados
 * (ver AppointmentStatusService para las transiciones de estado).
 */
class AppointmentService
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Calcula los slots de horario disponibles para un barbero en una fecha,
     * segun su horario propio (o el horario global de la barberia como
     * fallback), descontando las citas ya existentes y el margen de 10 min
     * si la fecha es hoy.
     */
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
            $settings = BarbershopSetting::cached();
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
                    'time' => $current->format('H:i'),
                    'label' => $current->format('g:i A'),
                    'end_time' => $current->copy()->addMinutes($duration)->format('H:i'),
                    'end_label' => $current->copy()->addMinutes($duration)->format('g:i A'),
                ];
            }

            $current->addMinutes($interval);
        }

        return $slots;
    }

    /**
     * Crea la cita tras validar que no haya conflictos. Efecto secundario:
     * dispara notificaciones a cliente/barbero/staff (ver AppointmentNotifier).
     */
    public function createAppointment(array $payload)
    {
        $this->ensureNoOverlap($payload);

        $appointment = $this->appointments->create($payload);

        // Notifica a cliente + barbero + recepcion/admin (resiliente a fallos).
        $this->notifier->created($appointment);

        return $appointment;
    }

    /**
     * Actualiza una cita existente (ej. reprogramacion) tras revalidar que
     * el nuevo horario no genere conflictos, ignorando la propia cita.
     */
    public function updateAppointment(string $appointmentId, array $payload): bool
    {
        $this->ensureNoOverlap($payload, $appointmentId);

        return $this->appointments->update($appointmentId, $payload);
    }

    /**
     * Valida las dos reglas de negocio que impiden guardar una cita:
     * 1) el mismo cliente no puede tener dos citas el mismo dia, y
     * 2) el barbero no puede tener dos citas que se solapen en horario.
     * Lanza una excepcion de dominio especifica si alguna regla se viola.
     */
    private function ensureNoOverlap(array $payload, ?string $ignoreAppointmentId = null): void
    {
        // 1. Verificar que el cliente no tenga otra cita ese mismo día
        if (! empty($payload['client_id'])) {
            $fecha = substr((string) $payload['fecha'], 0, 10);

            $hasClientConflict = $this->appointments->hasClientDayConflict(
                clientId: (string) $payload['client_id'],
                date: $fecha,
                ignoreAppointmentId: $ignoreAppointmentId,
            );

            if ($hasClientConflict) {
                $fechaFormatted = Carbon::parse($fecha)->translatedFormat('d \\d\\e F \\d\\e Y');
                throw new ClientAlreadyBookedException($fechaFormatted);
            }
        }

        // 2. Verificar que el barbero no tenga solapamiento de horario
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
