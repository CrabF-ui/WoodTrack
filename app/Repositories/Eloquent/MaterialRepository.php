<?php

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MaterialRepository implements MaterialRepositoryInterface
{
    public function getAll(): Collection
    {
        return Material::orderBy('nombre')->get();
    }

    public function findById(int $id): Material
    {
        return Material::findOrFail($id);
    }

    public function getByTipo(string $tipo): Collection
    {
        return Material::where('tipo', $tipo)->orderBy('nombre')->get();
    }

    public function getBajoStock(): Collection
    {
        return Material::bajoStock()
            ->orderByRaw("CASE tipo WHEN 'madera' THEN 1 WHEN 'herraje' THEN 2 WHEN 'acabado' THEN 3 END")
            ->orderBy('nombre')
            ->get();
    }

    public function create(array $data): Material
    {
        return Material::create($data);
    }

    public function update(int $id, array $data): Material
    {
        $material = Material::findOrFail($id);
        $material->update($data);

        return $material->refresh();
    }

    public function delete(int $id): bool
    {
        return Material::findOrFail($id)->delete();
    }
}
