<?php

namespace App\Repositories\Eloquent;

use App\Models\Entrega;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Services\EvidenciaEntregaService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntregaRepository implements EntregaRepositoryInterface
{
    public function __construct(private EvidenciaEntregaService $evidencias) {}

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

    public function getUnaPorPedido(?int $choferId = null, ?string $fecha = null): LengthAwarePaginator
    {
        $entregas = Entrega::query()
            ->with(['pedido.cliente', 'chofer'])
            ->withCount('fotos')
            ->select('entregas.*')
            ->selectSub(
                DB::table('entregas as intentos_conteo')
                    ->selectRaw('count(*)')
                    ->whereColumn('intentos_conteo.pedido_id', 'entregas.pedido_id'),
                'total_intentos',
            )
            ->whereColumn('entregas.id', '=', DB::raw('(select max(intentos.id) from entregas as intentos where intentos.pedido_id = entregas.pedido_id)'))
            ->when($choferId, fn ($query) => $query->where('chofer_id', $choferId))
            ->when($fecha, fn ($query) => $query->whereDate('fecha_asignada', $fecha))
            ->orderBy('fecha_asignada')
            ->orderByRaw('orden is null')
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $entregas->getCollection()->each(
            fn (Entrega $entrega) => $entrega->setAttribute('numero_intento', $entrega->total_intentos),
        );

        return $entregas;
    }

    public function getIntentosDePedido(int $pedidoId): Collection
    {
        return Entrega::query()
            ->with('chofer')
            ->where('pedido_id', $pedidoId)
            ->orderByDesc('id')
            ->get();
    }

    public function getIncidenciasConSituacion(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        $incidencias = Entrega::query()
            ->with(['pedido.cliente', 'chofer'])
            ->where('estado', Entrega::ESTADO_INCIDENCIA)
            ->whereBetween('estado_fecha_hora', [$desde, $hasta])
            ->orderBy('estado_fecha_hora')
            ->get();

        if ($incidencias->isEmpty()) {
            return $incidencias;
        }

        $intentosPosteriores = Entrega::query()
            ->whereIn('pedido_id', $incidencias->pluck('pedido_id')->unique())
            ->where('id', '>', $incidencias->min('id'))
            ->get(['id', 'pedido_id', 'estado'])
            ->groupBy('pedido_id');

        return $incidencias->each(function (Entrega $incidencia) use ($intentosPosteriores): void {
            $posteriores = $intentosPosteriores
                ->get($incidencia->pedido_id, collect())
                ->where('id', '>', $incidencia->id);

            $situacion = match (true) {
                $posteriores->isEmpty() => 'Sin atender',
                $posteriores->contains('estado', Entrega::ESTADO_ENTREGADA) => 'Resuelta',
                default => 'Reasignada',
            };

            $incidencia->setAttribute('situacion', $situacion);
        });
    }

    public function findById(int $id): Entrega
    {
        return Entrega::with(['pedido.cliente', 'chofer'])->findOrFail($id);
    }

    public function findWithEvidencia(int $id): Entrega
    {
        return Entrega::with(['pedido.cliente', 'pedido.detalles', 'chofer', 'fotos'])->findOrFail($id);
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

    public function contarEntregasUltimos7Dias(): array
    {
        $entregas = Entrega::query()
            ->whereBetween('fecha_asignada', [Carbon::today()->subDays(6), Carbon::today()])
            ->whereHas('pedido', fn ($query) => $query->whereNull('pedidos.deleted_at'))
            ->get(['estado']);

        return [
            'entregadas' => $entregas->where('estado', Entrega::ESTADO_ENTREGADA)->count(),
            'asignadas' => $entregas->count(),
        ];
    }
}
