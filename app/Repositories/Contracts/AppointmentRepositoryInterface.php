<?php

namespace App\Repositories\Contracts;

interface AppointmentRepositoryInterface extends BaseRepositoryInterface
{
    public function getByBarberAndDate(string $barberId, string $date);

    public function hasOverlap(string $barberId, string $date, string $startTime, string $endTime, ?string $ignoreAppointmentId = null): bool;
}
