<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\StockInsuficienteException;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PedidoRepository implements PedidoRepositoryInterface
{
    private const RELACIONES = ['cliente', 'detalles'];

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $pedido = Pedido::with('herrajes')->findOrFail($id);
            $materiales = $this->bloquearMateriales($pedido->herrajes->pluck('material_id')->all());

            foreach ($pedido->herrajes as $herraje) {
                $cantidad = $herraje->cantidad;
                $materiales[$herraje->material_id]->increment('stock_actual', $cantidad);

                MovimientoInventario::create([
                    'material_id' => $herraje->material_id,
                    'tipo' => 'entrada',
                    'cantidad' => $cantidad,
                    'motivo' => "Devolución por eliminación de pedido #{$pedido->id}",
                    'registrado_por' => auth()->id(),
                    'pedido_id' => $pedido->id,
                    'fecha' => now(),
                ]);
            }

            return $pedido->delete();
        });
    }

    public function getTrashed(): Collection
    {
        return Pedido::onlyTrashed()
            ->with('cliente')
            ->orderByDesc('deleted_at')
            ->get();
    }

    public function restore(int $id): Pedido
    {
        return DB::transaction(function () use ($id) {
            $pedido = Pedido::onlyTrashed()->findOrFail($id);
            $herrajes = $pedido->herrajes()->get();
            $materiales = $this->bloquearMateriales($herrajes->pluck('material_id')->all());
            $lineasHerrajes = $herrajes->map(fn ($herraje) => [
                'material_id' => $herraje->material_id,
                'cantidad' => $herraje->cantidad,
            ])->all();

            $this->validarStock($lineasHerrajes, $materiales);

            foreach ($herrajes as $herraje) {
                $cantidad = $herraje->cantidad;
                $materiales[$herraje->material_id]->decrement('stock_actual', $cantidad);

                MovimientoInventario::create([
                    'material_id' => $herraje->material_id,
                    'tipo' => 'salida',
                    'cantidad' => $cantidad,
                    'motivo' => "Restauración de pedido #{$pedido->id}",
                    'registrado_por' => auth()->id(),
                    'pedido_id' => $pedido->id,
                    'fecha' => now(),
                ]);
            }

            $pedido->restore();

            return $pedido;
        });
    }

    public function getAll(?string $estado = null): Collection
    {
        return Pedido::with(self::RELACIONES)
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            ->orderByDesc('fecha_pedido')
            ->get();
    }

    public function getFiltered(string $estado, ?string $search = null, ?string $estatus = null): LengthAwarePaginator
    {
        $query = Pedido::query()->with(self::RELACIONES);

        if ($estado === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($estado === 'todos') {
            $query->withTrashed();
        }

        if ($estatus !== null && in_array($estatus, Pedido::ESTADOS, true)) {
            $query->where('estado', $estatus);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($query) use ($search) {
                $query->whereHas('cliente', fn ($clienteQuery) => $clienteQuery->where('nombre', 'like', "%{$search}%"))
                    ->orWhere('estado', 'like', "%{$search}%");

                if (is_numeric($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderByDesc('fecha_pedido')->paginate(15)->withQueryString();
    }

    public function findById(int $id): Pedido
    {
        return Pedido::with(self::RELACIONES)->findOrFail($id);
    }

    public function create(array $data): Pedido
    {
        return DB::transaction(function () use ($data) {
            $detalles = $data['detalles'] ?? [];
            $herrajes = $data['herrajes'] ?? [];
            unset($data['detalles']);
            unset($data['herrajes']);

            $materiales = $this->bloquearMateriales($herrajes);
            $this->validarStock($herrajes, $materiales);

            $data['total'] = $this->calcularTotal($detalles);

            $pedido = Pedido::create($data);
            $pedido->detalles()->createMany($detalles);

            if ($herrajes !== []) {
                $pedido->herrajes()->createMany($herrajes);
            }

            $this->registrarSalidas($pedido, $herrajes, $materiales);

            return $pedido->load(self::RELACIONES);
        });
    }

    public function update(int $id, array $data): Pedido
    {
        return DB::transaction(function () use ($id, $data) {
            $pedido = Pedido::findOrFail($id);
            $tieneHerrajes = array_key_exists('herrajes', $data);
            $herrajes = $data['herrajes'] ?? [];
            unset($data['herrajes']);

            if ($tieneHerrajes) {
                $herrajesAnteriores = $pedido->herrajes()->get();
                $materiales = $this->bloquearMateriales(
                    $herrajesAnteriores->pluck('material_id')
                        ->merge(collect($herrajes)->pluck('material_id'))
                        ->unique()
                        ->values()
                        ->all()
                );

                foreach ($herrajesAnteriores as $herrajeAnterior) {
                    $material = $materiales[$herrajeAnterior->material_id];
                    $material->increment('stock_actual', $herrajeAnterior->cantidad);

                    MovimientoInventario::create([
                        'material_id' => $herrajeAnterior->material_id,
                        'tipo' => 'entrada',
                        'cantidad' => $herrajeAnterior->cantidad,
                        'motivo' => "Reversión por edición de pedido #{$pedido->id}",
                        'registrado_por' => auth()->id(),
                        'pedido_id' => $pedido->id,
                        'fecha' => now(),
                    ]);
                }

                $pedido->herrajes()->delete();

                $this->validarStock($herrajes, $materiales);
            }

            if (array_key_exists('detalles', $data)) {
                $detalles = $data['detalles'];
                unset($data['detalles']);

                $pedido->detalles()->delete();
                $pedido->detalles()->createMany($detalles);
                $data['total'] = $this->calcularTotal($detalles);
            }

            if ($tieneHerrajes) {
                if ($herrajes !== []) {
                    $pedido->herrajes()->createMany($herrajes);
                }

                $this->registrarSalidas($pedido, $herrajes, $materiales);
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

    /** @return array<int, Material> */
    private function bloquearMateriales(array $herrajes): array
    {
        $materiales = [];

        foreach ($herrajes as $herraje) {
            $materialId = is_array($herraje) ? $herraje['material_id'] : $herraje;
            if (isset($materiales[$materialId])) {
                continue;
            }

            $materiales[$materialId] = Material::where('id', $materialId)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $materiales;
    }

    private function validarStock(array $herrajes, array $materiales): void
    {
        $cantidadesPorMaterial = [];

        foreach ($herrajes as $herraje) {
            $materialId = $herraje['material_id'];
            $cantidadesPorMaterial[$materialId] = ($cantidadesPorMaterial[$materialId] ?? 0)
                + (float) $herraje['cantidad'];
        }

        foreach ($cantidadesPorMaterial as $materialId => $cantidadSolicitada) {
            $material = $materiales[$materialId];
            $stockDisponible = (float) $material->stock_actual;

            if ($cantidadSolicitada > $stockDisponible) {
                throw new StockInsuficienteException(
                    "Stock insuficiente de '{$material->nombre}': disponible {$stockDisponible}, solicitado {$cantidadSolicitada}."
                );
            }
        }
    }

    private function registrarSalidas(Pedido $pedido, array $herrajes, array $materiales): void
    {
        foreach ($herrajes as $herraje) {
            $material = $materiales[$herraje['material_id']];
            $cantidad = $herraje['cantidad'];

            MovimientoInventario::create([
                'material_id' => $herraje['material_id'],
                'tipo' => 'salida',
                'cantidad' => $cantidad,
                'motivo' => "Salida automática por pedido #{$pedido->id}",
                'registrado_por' => auth()->id(),
                'pedido_id' => $pedido->id,
                'fecha' => now(),
            ]);

            $material->decrement('stock_actual', $cantidad);
        }
    }
}
