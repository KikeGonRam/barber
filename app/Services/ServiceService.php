<?php

namespace App\Services;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;

class ServiceService
{
    public function __construct(private readonly ServiceRepositoryInterface $services) {}

    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->services->paginateWithFilters($filters, $perPage);
    }

    public function categories(): array
    {
        return $this->services->getCategories();
    }

    public function create(array $payload): Service
    {
        return $this->services->create($payload);
    }

    public function update(Service $service, array $payload): bool
    {
        return $this->services->update($service->id, $payload);
    }

    public function delete(Service $service): bool
    {
        return $this->services->delete($service->id);
    }
}
