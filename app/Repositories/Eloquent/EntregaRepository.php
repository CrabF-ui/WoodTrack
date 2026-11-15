<?php

namespace App\Repositories\Eloquent;

use App\Models\Entrega;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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

    public function contarEntregasDeHoy(): array
    {
        $estadosPendientes = [
            Entrega::ESTADO_PENDIENTE,
            Entrega::ESTADO_EN_CAMINO,
            Entrega::ESTADO_INCIDENCIA,
        ];

        $entregas = Entrega::query()
            ->whereDate('fecha_asignada', Carbon::today())
            ->whereHas('pedido', fn ($query) => $query->whereNull('pedidos.deleted_at'))
            ->whereIn('estado', [...$estadosPendientes, Entrega::ESTADO_ENTREGADA])
            ->get(['estado']);

        return [
            'entregadas' => $entregas->where('estado', Entrega::ESTADO_ENTREGADA)->count(),
            'pendientes' => $entregas->whereIn('estado', $estadosPendientes)->count(),
        ];
    }
}
