<?php

namespace App\Repositories\Eloquent;

use App\Exceptions\StockInsuficienteException;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Repositories\Contracts\InventarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventarioRepository implements InventarioRepositoryInterface
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    private const RELACIONES_MOVIMIENTO = ['material', 'registradoPor'];

    // ---------- Materiales ----------

    public function getAllMateriales(bool $soloBajoStock = false): Collection
    {
        return Material::query()
            ->when($soloBajoStock, fn ($q) => $q->whereColumn('stock_actual', '<=', 'stock_minimo'))
            ->orderBy('nombre')
            ->get();
    }

    public function findMaterialById(int $id): Material
    {
        return Material::findOrFail($id);
    }

    public function createMaterial(array $data): Material
    {
        return Material::create($data);
    }

    public function updateMaterial(int $id, array $data): Material
    {
        $material = Material::findOrFail($id);
        $material->update(Arr::except($data, ['stock_actual']));

        return $material;
    }

    public function deleteMaterial(int $id): void
    {
        Material::findOrFail($id)->delete();
    }

    public function restoreMaterial(int $id): Material
    {
        $material = Material::onlyTrashed()->findOrFail($id);
        $material->restore();

        return $material;
    }

    // ---------- Movimientos ----------

    public function getMovimientosByMaterial(int $materialId): Collection
    {
        Material::findOrFail($materialId);

        return MovimientoInventario::with(self::RELACIONES_MOVIMIENTO)
            ->where('material_id', $materialId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function getFilteredMovimientos(array $filters = []): LengthAwarePaginator
    {
        $query = MovimientoInventario::with(array_merge(self::RELACIONES_MOVIMIENTO, ['pedido']))
            ->when(($filters['estado'] ?? 'activos') === 'anulados', fn ($query) => $query->onlyTrashed())
            ->when(($filters['estado'] ?? 'activos') === 'todos', fn ($query) => $query->withTrashed())
            ->when(! empty($filters['material_id']), fn ($query) => $query->where('material_id', $filters['material_id']))
            ->when(! empty($filters['tipo']), fn ($query) => $query->where('tipo', $filters['tipo']))
            ->when(($filters['origen'] ?? '') === 'manual', fn ($query) => $query->whereNull('pedido_id'))
            ->when(($filters['origen'] ?? '') === 'pedido', fn ($query) => $query->whereNotNull('pedido_id'))
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $search = $filters['q'];
                $query->where(function ($query) use ($search) {
                    $query->whereHas('material', fn ($materialQuery) => $materialQuery->where('nombre', 'like', "%{$search}%"))
                        ->orWhere('motivo', 'like', "%{$search}%")
                        ->orWhereHas('registradoPor', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));

                    if (is_numeric($search)) {
                        $query->orWhere('pedido_id', (int) $search);
                    }
                });
            });

        return $query->orderByDesc('fecha')->orderByDesc('id')->paginate(15)->withQueryString();
    }

    public function registrarMovimiento(array $data): MovimientoInventario
    {
        return DB::transaction(function () use ($data) {
            $material = Material::lockForUpdate()->findOrFail($data['material_id']);

            $this->aplicarAlStock($material, $data['tipo'], (float) $data['cantidad']);

            $data['fecha'] ??= now()->toDateString();

            return MovimientoInventario::create($data)->load(self::RELACIONES_MOVIMIENTO);
        });
    }

    public function deleteMovimiento(int $id): void
    {
        DB::transaction(function () use ($id) {
            $movimiento = MovimientoInventario::findOrFail($id);
            $this->validarMovimientoManual($movimiento);
            $material = Material::lockForUpdate()->findOrFail($movimiento->material_id);

            $this->aplicarAlStock(
                $material,
                $this->tipoInverso($movimiento->tipo),
                (float) $movimiento->cantidad
            );

            $movimiento->delete();
        });
    }

    public function restoreMovimiento(int $id): MovimientoInventario
    {
        return DB::transaction(function () use ($id) {
            $movimiento = MovimientoInventario::onlyTrashed()->findOrFail($id);
            $this->validarMovimientoManual($movimiento);
            $material = Material::lockForUpdate()->findOrFail($movimiento->material_id);

            $this->aplicarAlStock($material, $movimiento->tipo, (float) $movimiento->cantidad);
            $movimiento->restore();

            return $movimiento->load(self::RELACIONES_MOVIMIENTO);
        });
    }

    // ---------- Auxiliares ----------

    private function aplicarAlStock(Material $material, string $tipo, float $cantidad): void
    {
        $delta = match ($tipo) {
            self::TIPO_ENTRADA => $cantidad,
            self::TIPO_SALIDA => -$cantidad,
            default => throw new InvalidArgumentException("Tipo de movimiento inválido: {$tipo}"),
        };

        $nuevoStock = round((float) $material->stock_actual + $delta, 2);

        if ($nuevoStock < 0) {
            $stockDisponible = (float) $material->stock_actual;
            $cantidadSolicitada = abs($delta);
            throw new StockInsuficienteException(
                "Stock insuficiente de '{$material->nombre}': disponible {$stockDisponible}, solicitado {$cantidadSolicitada}."
            );
        }

        $material->stock_actual = $nuevoStock;
        $material->save();
    }

    private function validarMovimientoManual(MovimientoInventario $movimiento): void
    {
        if ($movimiento->pedido_id !== null) {
            throw new StockInsuficienteException(
                "El movimiento pertenece al pedido #{$movimiento->pedido_id}; corrígelo modificando el pedido."
            );
        }
    }

    private function tipoInverso(string $tipo): string
    {
        return $tipo === self::TIPO_ENTRADA ? self::TIPO_SALIDA : self::TIPO_ENTRADA;
    }

    // --- ALERTA DE BAJO STOCK ---
    public function countBajoStock(): int
    {
        return Material::bajoStock()->count();
    }

    public function getStockActualVsMinimo(): \Illuminate\Support\Collection
    {
        return Material::query()->get()
            ->sort(function (Material $a, Material $b): int {
                $actualA = $this->decimalACentavos($a->stock_actual);
                $minimoA = $this->decimalACentavos($a->stock_minimo);
                $actualB = $this->decimalACentavos($b->stock_actual);
                $minimoB = $this->decimalACentavos($b->stock_minimo);

                if ($minimoA === null || $minimoA === 0 || $minimoB === null || $minimoB === 0) {
                    if (($minimoA === null || $minimoA === 0) && $minimoB !== null && $minimoB > 0) {
                        return 1;
                    }
                    if (($minimoB === null || $minimoB === 0) && $minimoA !== null && $minimoA > 0) {
                        return -1;
                    }
                } elseif ($actualA !== null && $actualB !== null) {
                    $ordenRelacion = $this->compararFracciones($actualA, $minimoA, $actualB, $minimoB);
                    if ($ordenRelacion !== 0) {
                        return $ordenRelacion;
                    }
                }

                return strcasecmp($a->nombre, $b->nombre);
            })
            ->map(function (Material $material): array {
                $actual = $this->decimalACentavos($material->stock_actual);
                $minimo = $this->decimalACentavos($material->stock_minimo);

                return [
                    'material' => $material,
                    // Un mínimo nulo o cero no permite calcular una relación; se ordena al final.
                    'relacion' => $minimo !== null && $minimo > 0 && $actual !== null
                        ? (float) $material->stock_actual / (float) $material->stock_minimo
                        : null,
                    'bajoStock' => $actual !== null && $minimo !== null && $actual <= $minimo,
                ];
            })
            ->values();
    }

    private function decimalACentavos(string|int|float|null $valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        [$entero, $decimal] = array_pad(explode('.', (string) $valor, 2), 2, '');

        return ((int) $entero * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');
    }

    private function compararFracciones(int $numeradorA, int $denominadorA, int $numeradorB, int $denominadorB): int
    {
        $direccion = 1;

        while (true) {
            $cocienteA = intdiv($numeradorA, $denominadorA);
            $cocienteB = intdiv($numeradorB, $denominadorB);

            if ($cocienteA !== $cocienteB) {
                return $direccion * ($cocienteA <=> $cocienteB);
            }

            $restoA = $numeradorA % $denominadorA;
            $restoB = $numeradorB % $denominadorB;

            if ($restoA === 0 || $restoB === 0) {
                return $direccion * ($restoA <=> $restoB);
            }

            [$numeradorA, $denominadorA] = [$denominadorA, $restoA];
            [$numeradorB, $denominadorB] = [$denominadorB, $restoB];
            $direccion *= -1;
        }
    }
}
