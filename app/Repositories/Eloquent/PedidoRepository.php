<?php

namespace App\Repositories\Eloquent;

use App\Models\Pedido;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PedidoRepository implements PedidoRepositoryInterface
{
    private const RELACIONES = ['cliente', 'detalles'];

    public function getAll(?string $estado = null): Collection
    {
        return Pedido::with(self::RELACIONES)
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->orderByDesc('fecha_pedido')
            ->get();
    }

    public function findById(int $id): Pedido
    {
        return Pedido::with(self::RELACIONES)->findOrFail($id);
    }

    public function create(array $data): Pedido
    {
        return DB::transaction(function () use ($data) {
            $detalles = $data['detalles'] ?? [];
            unset($data['detalles']);

            $data['total'] = $this->calcularTotal($detalles);

            $pedido = Pedido::create($data);
            $pedido->detalles()->createMany($detalles);

            return $pedido->load(self::RELACIONES);
        });
    }

    public function update(int $id, array $data): Pedido
    {
        return DB::transaction(function () use ($id, $data) {
            $pedido = Pedido::findOrFail($id);

            if (array_key_exists('detalles', $data)) {
                $detalles = $data['detalles'];
                unset($data['detalles']);

                $pedido->detalles()->delete();
                $pedido->detalles()->createMany($detalles);
                $data['total'] = $this->calcularTotal($detalles);
            }

            $pedido->update($data);

            return $pedido->load(self::RELACIONES);
        });
    }

    public function updateEstado(int $id, string $estado): Pedido
    {
        return $this->update($id, ['estado' => $estado]);
    }

    private function calcularTotal(array $detalles): float
    {
        return (float) collect($detalles)->sum(
            fn ($d) => $d['cantidad'] * $d['precio_unitario']
        );
    }
}