//PedidoRepositoryInterface.php
<?php

namespace App\Repositories\Contracts;

use App\Models\Pedido;
use Illuminate\Database\Eloquent\Collection;

interface PedidoRepositoryInterface
{
    //** Agregacion del SOFT DELETE */
    /** DELETE /api/pedidos/{id} */
    public function delete(int $id): bool;


    /** GET /api/pedidos (filtro opcional por estado) */
    public function getAll(?string $estado = null): Collection;

    /** GET /api/pedidos/{id} */
    public function findById(int $id): Pedido;

    /** POST /api/pedidos */
    public function create(array $data): Pedido;

    /** PUT /api/pedidos/{id} */
    public function update(int $id, array $data): Pedido;

    /** PUT /api/pedidos/{id}/estado */
    public function updateEstado(int $id, string $estado): Pedido;
}