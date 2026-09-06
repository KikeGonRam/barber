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
        // distinct()->pluck() no funciona con el driver de MongoDB (devuelve
        // solo null por cada documento en vez de los valores únicos); se
        // deduplica en PHP con unique() en su lugar.
        return $this->model->newQuery()
            ->pluck('categoria')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15)
    {
        return $this->model->newQuery()
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->where('nombre', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%");
            })
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
