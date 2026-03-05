<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15)
    {
        return $this->model->newQuery()
            ->when(isset($filters['categoria']) && $filters['categoria'] !== '', function ($query) use ($filters) {
                $query->where('categoria', $filters['categoria']);
            })
            ->when(isset($filters['tipo']) && $filters['tipo'] !== '', function ($query) use ($filters) {
                $query->where('tipo', $filters['tipo']);
            })
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function lowStockCount(): int
    {
        return $this->model->newQuery()
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->count();
    }
}
