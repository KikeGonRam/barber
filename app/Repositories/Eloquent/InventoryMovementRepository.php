<?php

namespace App\Repositories\Eloquent;

use App\Models\InventoryMovement;
use App\Repositories\Contracts\InventoryMovementRepositoryInterface;

class InventoryMovementRepository extends BaseRepository implements InventoryMovementRepositoryInterface
{
    public function __construct(InventoryMovement $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15)
    {
        return $this->model->newQuery()
            ->with(['product', 'user', 'appointment.client.user'])
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->whereHas('product', fn ($p) => $p->where('nombre', 'like', "%{$q}%"))
                    ->orWhere('motivo', 'like', "%{$q}%");
            })
            ->when(isset($filters['tipo']) && $filters['tipo'] !== '', function ($query) use ($filters) {
                $query->where('tipo', $filters['tipo']);
            })
            ->when(isset($filters['product_id']) && $filters['product_id'] !== '', function ($query) use ($filters) {
                $query->where('product_id', (string) $filters['product_id']);
            })
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->latest('fecha')
            ->paginate($perPage)
            ->withQueryString();
    }
}
