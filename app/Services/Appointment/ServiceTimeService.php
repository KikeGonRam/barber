<?php

namespace App\Services\Appointment;

use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Models\Appointment;

/**
 * Agregar tiempo a un servicio en curso (flujo de citas V2, etapa 3). El barbero lo pide desde el aviso de «tu servicio
 * termina en 5 min» (+10 / +15) o desde su agenda. Reglas:
 *  - Solo un servicio «en proceso», con una de las opciones permitidas y hasta un máximo acumulado.
 *  - Si el nuevo fin choca con la siguiente cita del barbero, no se extiende salvo que lo confirme (`forzar`): en ese
 *    caso se avisa al siguiente cliente que puede empezar tarde.
 *  - Al extender se avisa al cliente, se actualiza la hora de fin de la cita y se vuelve a armar el aviso de «por
 *    terminar» para el nuevo fin. El cambio queda en la bitácora de la cita (Activitylog).
 */
class ServiceTimeService
{
    public function __construct(private readonly AppointmentNotifier $notifier) {}

    /** @return list<int> */
    public function options(): array
    {
        return array_values(array_map('intval', (array) config('appointments.extension_options', [10, 15, 20, 30])));
    }

    /**
     * @return array{extended: bool, conflict: Appointment|null, appointment: Appointment}
     *
     * @throws InvalidAppointmentTransitionException si la cita no está en proceso
     * @throws \InvalidArgumentException si los minutos no son una opción válida o se pasa del máximo acumulado
     */
    public function extend(Appointment $appointment, int $minutes, bool $force = false): array
    {
        if ($appointment->getAttribute('estado') !== 'en_proceso') {
            throw new InvalidAppointmentTransitionException('Solo se puede agregar tiempo a un servicio en proceso.');
        }

        if (! in_array($minutes, $this->options(), true)) {
            throw new \InvalidArgumentException('Elige una de las opciones de tiempo: '.implode(', ', $this->options()).' minutos.');
        }

        $current = (int) $appointment->getAttribute('minutos_extra');
        $max = (int) config('appointments.max_extra_minutes', 60);
        if ($current + $minutes > $max) {
            throw new \InvalidArgumentException("Esta cita ya tiene {$current} min extra; el máximo permitido es {$max}.");
        }

        $currentEnd = $appointment->expectedServiceEnd();
        if (! $currentEnd) {
            throw new \InvalidArgumentException('No se pudo calcular el fin del servicio.');
        }
        $newEnd = $currentEnd->copy()->addMinutes($minutes);

        $conflict = $this->nextAppointmentBefore($appointment, $newEnd->format('H:i:s'));
        if ($conflict && ! $force) {
            return ['extended' => false, 'conflict' => $conflict, 'appointment' => $appointment];
        }

        $appointment->update([
            'minutos_extra' => $current + $minutes,
            'hora_fin' => $newEnd->format('H:i:00'),
            // Se vuelven a armar los avisos para el nuevo fin.
            'aviso_fin_enviado_en' => null,
            'ultimo_aviso_barbero_en' => null,
        ]);

        $this->notifier->serviceExtended($appointment, $minutes, $newEnd);
        if ($conflict) {
            $this->notifier->possibleDelay($conflict, $newEnd);
        }

        return ['extended' => true, 'conflict' => $conflict, 'appointment' => $appointment];
    }

    /** Siguiente cita activa del mismo barbero, el mismo día, que empieza antes de $newEndTime. */
    private function nextAppointmentBefore(Appointment $appointment, string $newEndTime): ?Appointment
    {
        return Appointment::query()
            ->where('barber_id', (string) $appointment->barber_id)
            ->where('fecha', $appointment->fecha)
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->where('hora_inicio', '>', (string) $appointment->hora_inicio)
            ->where('hora_inicio', '<', $newEndTime)
            ->orderBy('hora_inicio')
            ->first();
    }
}
