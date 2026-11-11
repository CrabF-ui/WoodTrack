<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListarEntregasRequest;
use App\Http\Requests\Api\CambiarEstadoEntregaRequest;
use App\Http\Requests\Api\RegistrarIncidenciaRequest;
use App\Http\Resources\EntregaResource;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntregaController extends Controller
{
    public function index(ListarEntregasRequest $request, EntregaRepositoryInterface $entregas): AnonymousResourceCollection
    {
        $filtros = $request->validated();

        return EntregaResource::collection($entregas->getForChofer(
            $request->user()->id,
            $filtros['fecha'] ?? null,
            $filtros['estado'] ?? null,
        ));
    }

    public function cambiarEstado(CambiarEstadoEntregaRequest $request, int $entrega, EntregaRepositoryInterface $entregas): JsonResource
    {
        $actualizada = $entregas->cambiarEstadoDeChofer(
            $request->user()->id,
            $entrega,
            $request->validated('estado'),
        );

        return new EntregaResource($actualizada);
    }

    public function registrarIncidencia(RegistrarIncidenciaRequest $request, int $entrega, EntregaRepositoryInterface $entregas): JsonResource
    {
        $actualizada = $entregas->registrarIncidenciaDeChofer(
            $request->user()->id,
            $entrega,
            $request->validated('motivo'),
        );

        return new EntregaResource($actualizada);
    }
}
