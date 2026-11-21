<?php

namespace App\Services;

use App\Models\Entrega;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EvidenciaEntregaService
{
    public const MOTIVO_MINIMO = 10;

    public const MAXIMO_FOTOS = 3;

    public function contenidoFirma(string $ruta): ?string
    {
        $disco = Storage::disk('evidencias');
        if (! $disco->exists($ruta)) {
            return null;
        }

        $contenido = $disco->get($ruta);

        try {
            return Crypt::decryptString($contenido);
        } catch (DecryptException) {
            return $contenido;
        }
    }

    public function firmaComoDataUri(string $ruta): ?string
    {
        $contenido = $this->contenidoFirma($ruta);
        if ($contenido === null) {
            return null;
        }

        $tipo = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        if (! in_array($tipo, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return "data:{$tipo};base64,".base64_encode($contenido);
    }

    /** @return array{dataUri: string, width: int, height: int}|null */
    public function fotoComoDataUri(string $ruta, int $anchoMaximo = 800, int $calidad = 75): ?array
    {
        $disco = Storage::disk('evidencias');
        if (! $disco->exists($ruta)) {
            return null;
        }

        $origen = @imagecreatefromstring($disco->get($ruta));
        if ($origen === false) {
            return null;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $anchoDestino = min($ancho, $anchoMaximo);
        $altoDestino = max(1, (int) round($alto * ($anchoDestino / $ancho)));
        $destino = imagecreatetruecolor($anchoDestino, $altoDestino);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $anchoDestino, $altoDestino, $ancho, $alto);

        ob_start();
        imagejpeg($destino, null, $calidad);
        $contenido = ob_get_clean();
        imagedestroy($origen);
        imagedestroy($destino);

        if ($contenido === false) {
            return null;
        }

        return [
            'dataUri' => 'data:image/jpeg;base64,'.base64_encode($contenido),
            'width' => $anchoDestino,
            'height' => $altoDestino,
        ];
    }

    public function registrarEntrega(
        Entrega $entrega,
        string $receptorNombre,
        array $fotos,
        ?UploadedFile $firma = null,
        ?string $motivoSinFirma = null,
    ): Entrega {
        $receptorNombre = trim($receptorNombre);
        $motivoSinFirma = $motivoSinFirma === null ? null : trim($motivoSinFirma);

        if (mb_strlen($receptorNombre) < 2 || mb_strlen($receptorNombre) > 120) {
            throw ValidationException::withMessages([
                'receptor_nombre' => 'El nombre del receptor debe tener entre 2 y 120 caracteres.',
            ]);
        }

        if (count($fotos) < 1 || count($fotos) > self::MAXIMO_FOTOS) {
            throw ValidationException::withMessages([
                'fotos' => 'Debe registrar entre 1 y 3 fotos.',
            ]);
        }

        if ($firma === null && ($motivoSinFirma === null || mb_strlen($motivoSinFirma) < self::MOTIVO_MINIMO)) {
            throw ValidationException::withMessages([
                'motivo_sin_firma' => 'Indique un motivo de al menos 10 caracteres cuando no haya firma.',
            ]);
        }

        if ($firma !== null && $motivoSinFirma !== null) {
            throw ValidationException::withMessages([
                'firma' => 'No puede registrar una firma y un motivo de falta de firma al mismo tiempo.',
            ]);
        }

        $this->rechazarSiTieneEvidencia($entrega);
        $archivosGuardados = [];

        try {
            return DB::transaction(function () use ($entrega, $receptorNombre, $fotos, $firma, $motivoSinFirma, &$archivosGuardados): Entrega {
                $entrega->receptor_nombre = $receptorNombre;
                $entrega->sin_firma = $firma === null;
                $entrega->motivo_sin_firma = $firma === null ? $motivoSinFirma : null;
                $entrega->firma_path = $firma === null ? null : $this->guardarFirma($firma, "entregas/{$entrega->id}/firma", $archivosGuardados);
                $entrega->save();

                foreach ($fotos as $foto) {
                    $ruta = $this->guardarArchivo($foto, "entregas/{$entrega->id}/fotos", $archivosGuardados);
                    $entrega->fotos()->create(['ruta' => $ruta]);
                }

                return $entrega;
            });
        } catch (Throwable $exception) {
            $this->borrarArchivos($archivosGuardados);
            throw $exception;
        }
    }

    public function registrarIncidencia(Entrega $entrega, string $motivo): Entrega
    {
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < self::MOTIVO_MINIMO) {
            throw ValidationException::withMessages([
                'motivo' => 'El motivo de la incidencia debe tener al menos 10 caracteres.',
            ]);
        }

        $this->rechazarSiTieneEvidencia($entrega);
        $entrega->incidencia_motivo = $motivo;
        $entrega->save();

        return $entrega;
    }

    private function rechazarSiTieneEvidencia(Entrega $entrega): void
    {
        $entrega->loadCount('fotos');

        if ($entrega->fotos_count > 0 || $entrega->firma_path !== null || $entrega->receptor_nombre !== null || $entrega->incidencia_motivo !== null) {
            throw ValidationException::withMessages([
                'evidencia' => 'Esta entrega ya tiene evidencia registrada.',
            ]);
        }
    }

    private function guardarArchivo(UploadedFile $archivo, string $directorio, array &$archivosGuardados): string
    {
        $uuid = (string) Str::uuid();
        $extension = $archivo->guessExtension();
        $nombre = $uuid.($extension === null ? '' : ".{$extension}");
        $ruta = "{$directorio}/{$nombre}";

        Storage::disk('evidencias')->putFileAs($directorio, $archivo, $nombre);
        $archivosGuardados[] = $ruta;

        return $ruta;
    }

    private function guardarFirma(UploadedFile $firma, string $directorio, array &$archivosGuardados): string
    {
        $uuid = (string) Str::uuid();
        $extension = $firma->guessExtension();
        $nombre = $uuid.($extension === null ? '' : ".{$extension}");
        $ruta = "{$directorio}/{$nombre}";

        Storage::disk('evidencias')->put($ruta, Crypt::encryptString($firma->getContent()));
        $archivosGuardados[] = $ruta;

        return $ruta;
    }

    private function borrarArchivos(array $rutas): void
    {
        if ($rutas !== []) {
            Storage::disk('evidencias')->delete($rutas);
        }
    }
}
