<?php

namespace App\Repositories\Eloquent;

use App\Models\Entrega;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EntregaRepository implements EntregaRepositoryInterface
{
    public function getFiltered(?int $choferId = null, ?string $fecha = null): LengthAwarePaginator
    {
        return Entrega::with(['pedido.cliente', 'chofer'])
            ->when($choferId, fn ($query) => $query->where('chofer_id', $choferId))
            ->when($fecha, fn ($query) => $query->whereDate('fecha_asignada', $fecha))
            ->orderBy('fecha_asignada')
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function findById(int $id): Entrega
    {
        return Entrega::with(['pedido.cliente', 'chofer'])->findOrFail($id);
    }

    public function create(array $data): Entrega
    {
        return Entrega::create($data);
    }

    public function update(int $id, array $data): Entrega
    {
        $entrega = Entrega::findOrFail($id);
        $entrega->update($data);

        return $entrega->refresh()->load(['pedido.cliente', 'chofer']);
    }
}
