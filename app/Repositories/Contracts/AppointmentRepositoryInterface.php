<?php

namespace App\Repositories\Contracts;

interface AppointmentRepositoryInterface extends BaseRepositoryInterface
{
    public function getByBarberAndDate(int $barberId, string $date);

    public function hasOverlap(int $barberId, string $date, string $startTime, string $endTime, ?int $ignoreAppointmentId = null): bool;
}
