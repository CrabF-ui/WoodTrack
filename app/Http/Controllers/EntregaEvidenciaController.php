<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Services\EvidenciaEntregaService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntregaEvidenciaController extends Controller
{
    public function __construct(
        private EntregaRepositoryInterface $entregas,
        private EvidenciaEntregaService $evidencias,
    ) {}

    public function show(int $entrega): View
    {
        return view('entregas.evidencia', [
            'entrega' => $this->entregas->findWithEvidencia($entrega),
        ]);
    }

    public function firma(int $entrega): Response
    {
        $model = $this->entregas->findWithEvidencia($entrega);
        abort_if(blank($model->firma_path), 404);

        $contenido = $this->evidencias->contenidoFirma($model->firma_path);
        abort_if($contenido === null, 404);

        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        if (! in_array($tipo, ['image/png', 'image/jpeg'], true)) {
            $tipo = 'application/octet-stream';
        }

        return response($contenido, 200, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'inline; filename="'.basename($model->firma_path).'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function foto(int $entrega, int $foto): StreamedResponse
    {
        $model = $this->entregas->findWithEvidencia($entrega);
        $ruta = $model->fotos()->findOrFail($foto)->ruta;

        return $this->respuestaImagen($ruta);
    }

    private function respuestaImagen(string $ruta): StreamedResponse
    {
        /** @var FilesystemAdapter $disco */
        $disco = Storage::disk('evidencias');
        abort_unless($disco->exists($ruta), 404);

        return $disco->response($ruta, null, [
            'Content-Type' => $disco->mimeType($ruta),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
