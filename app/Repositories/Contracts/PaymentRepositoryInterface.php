<?php

namespace App\Repositories\Contracts;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15);

    public function existsForAppointment(int $appointmentId): bool;
}
