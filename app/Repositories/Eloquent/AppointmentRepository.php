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
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('hora_inicio', [$startTime, $endTime])
                    ->orWhereBetween('hora_fin', [$startTime, $endTime])
                    ->orWhere(function ($nested) use ($startTime, $endTime) {
                        $nested->where('hora_inicio', '<=', $startTime)
                            ->where('hora_fin', '>=', $endTime);
                    });
            });

        if ($ignoreAppointmentId) {
            $query->whereKeyNot($ignoreAppointmentId);
        }

        return $query->exists();
    }
}
