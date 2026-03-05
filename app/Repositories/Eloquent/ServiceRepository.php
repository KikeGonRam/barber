<?php

namespace App\Repositories\Eloquent;

use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryInterface;

class ServiceRepository extends BaseRepository implements ServiceRepositoryInterface
{
    public function __construct(Service $model)
    {
        parent::__construct($model);
    }

    public function getCategories(): array
    {
        return $this->model->newQuery()
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->filter()
            ->values()
            ->all();
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15)
    {
        return $this->model->newQuery()
            ->when(isset($filters['categoria']) && $filters['categoria'] !== '', function ($query) use ($filters) {
                $query->where('categoria', $filters['categoria']);
            })
            ->when(isset($filters['activo']) && $filters['activo'] !== '', function ($query) use ($filters) {
                $query->where('activo', (bool) $filters['activo']);
            })
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();
    }
}
