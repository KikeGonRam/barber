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

    public function getByBarberAndDate(string $barberId, string $date)
    {
        return $this->model->newQuery()
            ->where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->orderBy('hora_inicio')
            ->get();
    }

    public function hasOverlap(string $barberId, string $date, string $startTime, string $endTime, ?string $ignoreAppointmentId = null): bool
    {
        $query = $this->model->newQuery()
            ->where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->whereNotIn('estado', ['cancelada', 'no_asistio'])
            ->where('hora_inicio', '<', $endTime)
            ->where('hora_fin', '>', $startTime);

        if ($ignoreAppointmentId) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        return $query->exists();
    }

    public function hasClientDayConflict(string $clientId, string $date, ?string $ignoreAppointmentId = null): bool
    {
        $query = $this->model->newQuery()
            ->where('client_id', $clientId)
            ->whereDate('fecha', $date)
            ->whereNotIn('estado', ['cancelada', 'no_asistio']);

        if ($ignoreAppointmentId) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        return $query->exists();
    }
}
