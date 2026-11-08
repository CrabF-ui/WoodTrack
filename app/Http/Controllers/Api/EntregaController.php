<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListarEntregasRequest;
use App\Http\Resources\EntregaResource;
use App\Repositories\Contracts\EntregaRepositoryInterface;
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
}
