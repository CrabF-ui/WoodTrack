<?php

namespace App\Repositories\Contracts;

use App\Models\Material;
use App\Models\MovimientoInventario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InventarioRepositoryInterface
{
    /** GET /api/materiales (opcional: solo los de bajo stock) */
    public function getAllMateriales(bool $soloBajoStock = false): Collection;

    public function findMaterialById(int $id): Material;

    /** POST /api/materiales */
    public function createMaterial(array $data): Material;

    /** PUT /api/materiales/{id} (no modifica el stock) */
    public function updateMaterial(int $id, array $data): Material;

    /** Baja lógica (SoftDelete) */
    public function deleteMaterial(int $id): void;

    public function restoreMaterial(int $id): Material;

    public function getMovimientosByMaterial(int $materialId): Collection;

    public function getFilteredMovimientos(array $filters = []): LengthAwarePaginator;

    /** PUT /api/materiales/{id}/stock: registra entrada o salida y actualiza el stock */
    public function registrarMovimiento(array $data): MovimientoInventario;

    /** Anula el movimiento (SoftDelete) y revierte su efecto en el stock */
    public function deleteMovimiento(int $id): void;

    /** Restaura el movimiento y vuelve a aplicar su efecto en el stock */
    public function restoreMovimiento(int $id): MovimientoInventario;
}
