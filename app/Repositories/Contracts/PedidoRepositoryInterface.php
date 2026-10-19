<?php

namespace App\Repositories\Contracts;

use App\Models\Pedido;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PedidoRepositoryInterface
{
    //** Agregacion del SOFT DELETE */
    /** DELETE /api/pedidos/{id} */
    public function delete(int $id): bool;

    public function getTrashed(): Collection;

    public function restore(int $id): Pedido;


    /** GET /api/pedidos (filtro opcional por estado) */
    public function getAll(?string $estado = null): Collection;

    public function getFiltered(string $estado, ?string $search = null, ?string $estatus = null): LengthAwarePaginator;

    /** GET /api/pedidos/{id} */
    public function findById(int $id): Pedido;

    /** POST /api/pedidos */
    public function create(array $data): Pedido;

    /** PUT /api/pedidos/{id} */
    public function update(int $id, array $data): Pedido;

    /** PUT /api/pedidos/{id}/estado */
    public function updateEstado(int $id, string $estado): Pedido;
}
