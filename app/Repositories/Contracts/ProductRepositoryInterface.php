<?php

namespace App\Repositories\Contracts;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15);

    public function lowStockCount(): int;
}
