<?php

namespace App\Repositories\Contracts;

interface ServiceRepositoryInterface extends BaseRepositoryInterface
{
    public function getCategories(): array;

    public function paginateWithFilters(array $filters = [], int $perPage = 15);
}
