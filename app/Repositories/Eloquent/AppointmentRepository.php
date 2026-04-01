<?php

namespace App\Repositories\Eloquent;

use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;

class AppointmentRepository extends BaseRepository implements AppointmentRepositoryInterface
{
    public function __construct(Appointment $model)
    {
        parent::__construct($model);
    }

    public function getByBarberAndDate(int $barberId, string $date)
    {
        return $this->model->newQuery()
            ->where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->orderBy('hora_inicio')
            ->get();
    }

    public function hasOverlap(int $barberId, string $date, string $startTime, string $endTime, ?int $ignoreAppointmentId = null): bool
    {
        $query = $this->model->newQuery()
            ->where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->whereNotIn('estado', ['cancelada', 'no_asistio'])
            // Solapamiento estricto: existing.start < new.end AND existing.end > new.start.
            // Permite slots contiguos sin conflicto (ej. 10:00-10:30 y 10:30-11:00).
            ->where('hora_inicio', '<', $endTime)
            ->where('hora_fin', '>', $startTime);

        if ($ignoreAppointmentId) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        return $query->exists();
    }
}
