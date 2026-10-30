<?php

namespace App\Repositories\Contracts;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClienteRepositoryInterface
{
    public function getFiltered(string $estado, ?string $search = null): LengthAwarePaginator;

    public function findById(int $id): Cliente;

    public function create(array $data): Cliente;

    public function update(int $id, array $data): Cliente;

    public function delete(int $id): bool;

    public function restore(int $id): Cliente;
}
