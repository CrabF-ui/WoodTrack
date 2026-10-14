<?php

namespace App\Repositories\Contracts;

use App\Models\Material;
use Illuminate\Database\Eloquent\Collection;

interface MaterialRepositoryInterface
{
    public function getAll(): Collection;

    public function findById(int $id): Material;

    public function getByTipo(string $tipo): Collection;

    public function create(array $data): Material;

    public function update(int $id, array $data): Material;

    public function delete(int $id): bool;
}
