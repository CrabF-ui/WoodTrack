<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\EntregaRepositoryInterface;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntregaEvidenciaController extends Controller
{
    public function __construct(private EntregaRepositoryInterface $entregas)
    {
    }

    public function show(int $entrega): View
    {
        return view('entregas.evidencia', [
            'entrega' => $this->entregas->findWithEvidencia($entrega),
        ]);
    }

    public function firma(int $entrega): StreamedResponse
    {
        $model = $this->entregas->findWithEvidencia($entrega);
        abort_if(blank($model->firma_path), 404);

        return $this->respuestaImagen($model->firma_path);
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
            'Cache-Control' => 'private',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
