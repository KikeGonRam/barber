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
            ->when(isset($filters['tipo']) && $filters['tipo'] !== '', function ($query) use ($filters) {
                $query->where('tipo', $filters['tipo']);
            })
            ->when(isset($filters['product_id']) && $filters['product_id'] !== '', function ($query) use ($filters) {
                $query->where('product_id', (string) $filters['product_id']);
            })
            ->latest('fecha')
            ->paginate($perPage)
            ->withQueryString();
    }
}
