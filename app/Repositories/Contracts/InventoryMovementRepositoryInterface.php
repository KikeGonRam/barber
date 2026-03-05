<?php

namespace App\Repositories\Contracts;

interface InventoryMovementRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15);
}
