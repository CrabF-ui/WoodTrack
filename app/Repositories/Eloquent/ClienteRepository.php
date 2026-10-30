<?php

namespace App\Repositories\Eloquent;

use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClienteRepository implements ClienteRepositoryInterface
{
    public function getFiltered(string $estado, ?string $search = null): LengthAwarePaginator
    {
        $query = Cliente::query();

        if ($estado === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($estado === 'todos') {
            $query->withTrashed();
        }

        if ($search !== null && $search !== '') {
            $query->where('nombre', 'like', "%{$search}%");
        }

        return $query->orderBy('nombre')->paginate(15)->withQueryString();
    }

    public function findById(int $id): Cliente
    {
        return Cliente::findOrFail($id);
    }

    public function create(array $data): Cliente
    {
        return Cliente::create($data);
    }

    public function update(int $id, array $data): Cliente
    {
        $cliente = $this->findById($id);
        $cliente->update($data);

        return $cliente->refresh();
    }

    public function delete(int $id): bool
    {
        return (bool) $this->findById($id)->delete();
    }

    public function restore(int $id): Cliente
    {
        $cliente = Cliente::onlyTrashed()->findOrFail($id);
        $cliente->restore();

        return $cliente;
    }
}
