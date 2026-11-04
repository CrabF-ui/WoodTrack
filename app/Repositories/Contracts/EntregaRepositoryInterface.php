<?php

namespace App\Repositories\Contracts;

use App\Models\Entrega;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EntregaRepositoryInterface
{
    public function getFiltered(?int $choferId = null, ?string $fecha = null): LengthAwarePaginator;

    public function findById(int $id): Entrega;

    public function create(array $data): Entrega;

    public function update(int $id, array $data): Entrega;
}
