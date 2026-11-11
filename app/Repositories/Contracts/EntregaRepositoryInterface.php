<?php

namespace App\Repositories\Contracts;

use App\Models\Entrega;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface EntregaRepositoryInterface
{
    public function getFiltered(?int $choferId = null, ?string $fecha = null): LengthAwarePaginator;

    public function getForChofer(int $choferId, ?string $fecha = null, ?string $estado = null): Collection;

    public function cambiarEstadoDeChofer(int $choferId, int $entregaId, string $estado): Entrega;

    public function registrarIncidenciaDeChofer(int $choferId, int $entregaId, string $motivo): Entrega;

    public function entregarDeChofer(
        int $choferId,
        int $entregaId,
        string $receptorNombre,
        array $fotos,
        ?UploadedFile $firma,
        ?string $motivoSinFirma,
    ): Entrega;

    public function findById(int $id): Entrega;

    public function findWithEvidencia(int $id): Entrega;

    public function create(array $data): array;

    public function update(int $id, array $data): array;
}
