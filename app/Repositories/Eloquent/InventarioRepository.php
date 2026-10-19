<?php

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Repositories\Contracts\InventarioRepositoryInterface;
use DomainException;
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
            throw new DomainException("Stock insuficiente para el material '{$material->nombre}'.");
        }

        $material->stock_actual = $nuevoStock;
        $material->save();
    }

    private function tipoInverso(string $tipo): string
    {
        return $tipo === self::TIPO_ENTRADA ? self::TIPO_SALIDA : self::TIPO_ENTRADA;
    }
}
