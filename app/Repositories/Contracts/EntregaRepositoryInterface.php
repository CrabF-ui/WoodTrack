<?php

namespace App\Repositories\Contracts;

use App\Models\Entrega;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface EntregaRepositoryInterface
{
    public function getFiltered(?int $choferId = null, ?string $fecha = null): LengthAwarePaginator;

    public function getForChofer(int $choferId, ?string $fecha = null, ?string $estado = null): Collection;

    public function findById(int $id): Entrega;

    public function create(array $data): array;

    public function update(int $id, array $data): array;
}
