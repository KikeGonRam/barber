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
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->where('nombre', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->when(isset($filters['categoria']) && $filters['categoria'] !== '', function ($query) use ($filters) {
                $query->where('categoria', $filters['categoria']);
            })
            ->when(isset($filters['tipo']) && $filters['tipo'] !== '', function ($query) use ($filters) {
                $query->where('tipo', $filters['tipo']);
            })
            // whereRaw con $expr: comparar stock_actual <= stock_minimo (dos campos
            // del mismo documento) no se puede expresar con where() normal en MongoDB.
            ->when(! empty($filters['bajo_stock']), function ($query) {
                $query->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]]);
            })
            ->orderBy('nombre')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function lowStockCount(): int
    {
        return $this->model->newQuery()
            ->whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])
            ->count();
    }
}
