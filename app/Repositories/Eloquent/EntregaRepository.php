<?php

namespace App\Repositories\Eloquent;

use App\Models\Entrega;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Services\EvidenciaEntregaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntregaRepository implements EntregaRepositoryInterface
{
    public function __construct(private EvidenciaEntregaService $evidencias)
    {
    }

    public function getForChofer(int $choferId, ?string $fecha = null, ?string $estado = null): Collection
    {
        $query = Entrega::query()
            ->with(['pedido.cliente', 'pedido.detalles'])
            ->where('chofer_id', $choferId)
            ->whereHas('pedido', fn ($query) => $query->whereNull('deleted_at'))
            ->when($fecha, fn ($query) => $query->whereDate('fecha_asignada', $fecha))
            ->when($estado, fn ($query) => $query->where('estado', $estado));

        if ($fecha === null && $estado === null) {
            $query->whereIn('estado', Entrega::ESTADOS_ACTIVOS);
        }

        return $query->orderBy('fecha_asignada')
            ->orderByRaw('orden is null')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public function cambiarEstadoDeChofer(int $choferId, int $entregaId, string $estado): Entrega
    {
        return DB::transaction(function () use ($choferId, $entregaId, $estado): Entrega {
            $entrega = Entrega::query()
                ->whereKey($entregaId)
                ->where('chofer_id', $choferId)
                ->whereHas('pedido', fn ($query) => $query->whereNull('deleted_at'))
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($entrega->estado, [Entrega::ESTADO_ENTREGADA, Entrega::ESTADO_INCIDENCIA], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Esta entrega ya fue cerrada y no puede cambiar de estado.',
                ]);
            }

            if ($entrega->estado !== $estado) {
                $entrega->cambiarEstado($estado);
            }

            return $entrega->refresh()->load(['pedido.cliente', 'pedido.detalles']);
        });
    }

    public function registrarIncidenciaDeChofer(int $choferId, int $entregaId, string $motivo): Entrega
    {
        return DB::transaction(function () use ($choferId, $entregaId, $motivo): Entrega {
            $entrega = Entrega::query()
                ->whereKey($entregaId)
                ->where('chofer_id', $choferId)
                ->whereHas('pedido', fn ($query) => $query->whereNull('deleted_at'))
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($entrega->estado, Entrega::ESTADOS_ACTIVOS, true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Esta entrega ya fue cerrada y no puede cambiar de estado.',
                ]);
            }

            $this->evidencias->registrarIncidencia($entrega, $motivo);
            $entrega->cambiarEstado(Entrega::ESTADO_INCIDENCIA);

            return $entrega->refresh()->load(['pedido.cliente', 'pedido.detalles']);
        });
    }

    public function entregarDeChofer(
        int $choferId,
        int $entregaId,
        string $receptorNombre,
        array $fotos,
        ?UploadedFile $firma,
        ?string $motivoSinFirma,
    ): Entrega {
        return DB::transaction(function () use ($choferId, $entregaId, $receptorNombre, $fotos, $firma, $motivoSinFirma): Entrega {
            $entrega = Entrega::query()
                ->whereKey($entregaId)
                ->where('chofer_id', $choferId)
                ->whereHas('pedido', fn ($query) => $query->whereNull('deleted_at'))
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($entrega->estado, [Entrega::ESTADO_ENTREGADA, Entrega::ESTADO_INCIDENCIA], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Esta entrega ya fue cerrada y no puede cambiar de estado.',
                ]);
            }

            if ($entrega->estado !== Entrega::ESTADO_EN_CAMINO) {
                throw ValidationException::withMessages([
                    'estado' => 'La entrega debe estar en camino para registrarla como entregada.',
                ]);
            }

            $entrega->load(['pedido.cliente', 'pedido.detalles']);
            $entrega->cambiarEstado(Entrega::ESTADO_ENTREGADA);

            return $this->evidencias->registrarEntrega($entrega, $receptorNombre, $fotos, $firma, $motivoSinFirma);
        });
    }

    public function getFiltered(?int $choferId = null, ?string $fecha = null): LengthAwarePaginator
    {
        return Entrega::with(['pedido.cliente', 'chofer'])
            ->when($choferId, fn ($query) => $query->where('chofer_id', $choferId))
            ->when($fecha, fn ($query) => $query->whereDate('fecha_asignada', $fecha))
            ->orderBy('fecha_asignada')
            ->orderByRaw('orden is null')
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
    }

    public function findById(int $id): Entrega
    {
        return Entrega::with(['pedido.cliente', 'chofer'])->findOrFail($id);
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $recorridas = $this->desplazarEntregas($data['chofer_id'], $data['fecha_asignada'], $data['orden'] ?? null);

            return ['entrega' => Entrega::create($data), 'recorridas' => $recorridas];
        });
    }

    public function update(int $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data): array {
            $entrega = Entrega::findOrFail($id);
            $destinoChofer = $data['chofer_id'] ?? $entrega->chofer_id;
            $destinoFecha = $data['fecha_asignada'] ?? $entrega->fecha_asignada->toDateString();
            $destinoOrden = array_key_exists('orden', $data) ? $data['orden'] : $entrega->orden;
            $cambiaDestino = (int) $destinoChofer !== (int) $entrega->chofer_id
                || $destinoFecha !== $entrega->fecha_asignada->toDateString()
                || $destinoOrden != $entrega->orden;
            $recorridas = $cambiaDestino
                ? $this->desplazarEntregas($destinoChofer, $destinoFecha, $destinoOrden, $entrega->id)
                : 0;
            $entrega->update($data);

            return ['entrega' => $entrega->refresh()->load(['pedido.cliente', 'chofer']), 'recorridas' => $recorridas];
        });
    }

    private function desplazarEntregas(int $choferId, string $fecha, mixed $orden, ?int $excluirId = null): int
    {
        if ($orden === null || $orden === '') {
            return 0;
        }

        $ocupado = Entrega::query()
            ->where('chofer_id', $choferId)
            ->whereDate('fecha_asignada', $fecha)
            ->whereIn('estado', Entrega::ESTADOS_ACTIVOS)
            ->where('orden', $orden)
            ->when($excluirId, fn ($query) => $query->where('id', '!=', $excluirId))
            ->exists();

        if (! $ocupado) {
            return 0;
        }

        $query = Entrega::query()
            ->where('chofer_id', $choferId)
            ->whereDate('fecha_asignada', $fecha)
            ->whereIn('estado', Entrega::ESTADOS_ACTIVOS)
            ->where('orden', '>=', $orden)
            ->when($excluirId, fn ($query) => $query->where('id', '!=', $excluirId))
            ->orderByDesc('orden')
            ->lockForUpdate();
        $entregas = $query->get();
        foreach ($entregas as $entrega) {
            $entrega->orden++;
            $entrega->save();
        }

        return $entregas->count();
    }
}
