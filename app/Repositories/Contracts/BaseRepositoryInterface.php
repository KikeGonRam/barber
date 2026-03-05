<?php

namespace App\Repositories\Contracts;

interface BaseRepositoryInterface
{
    public function all(array $columns = ['*']);

    public function find(int $id, array $columns = ['*']);

    public function create(array $data);

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
