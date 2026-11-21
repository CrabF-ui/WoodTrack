<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReportePdf
{
    public function generar(string $vista, array $datos, string $archivo): Response
    {
        $nombre = pathinfo($archivo, PATHINFO_FILENAME);
        $nombre = Str::of($nombre)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-_')
            ->value();
        $nombre = ($nombre !== '' ? $nombre : 'reporte').'.pdf';

        $pdf = Pdf::loadView($vista, $datos)
            ->setPaper(config('reportes.papel'), config('reportes.orientacion'))
            ->setOptions([
                'defaultFont' => config('reportes.fuente'),
                'fontDir' => storage_path('fonts'),
                'fontCache' => storage_path('fonts'),
                'isRemoteEnabled' => false,
            ], true);

        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();
        $fuente = $fontMetrics->getFont(config('reportes.fuente'), 'normal');
        $texto = 'Página {PAGE_NUM} de {PAGE_COUNT}';
        $tamano = 9;
        $x = ($canvas->get_width() - $fontMetrics->getTextWidth($texto, $fuente, $tamano)) / 2;
        $y = $canvas->get_height() - 36;
        $canvas->page_text($x, $y, $texto, $fuente, $tamano, [0.4, 0.4, 0.4]);

        return $pdf->stream($nombre);
    }
}
