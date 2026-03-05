<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function all(array $columns = ['*'])
    {
        return $this->model->newQuery()->get($columns);
    }

    public function find(int $id, array $columns = ['*'])
    {
        return $this->model->newQuery()->find($id, $columns);
    }

    public function create(array $data)
    {
        return $this->model->newQuery()->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $entity = $this->model->newQuery()->findOrFail($id);

        return $entity->update($data);
    }

    public function delete(int $id): bool
    {
        $entity = $this->model->newQuery()->findOrFail($id);

        return (bool) $entity->delete();
    }
}
