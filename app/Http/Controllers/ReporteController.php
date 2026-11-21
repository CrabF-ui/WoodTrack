<?php

namespace App\Http\Controllers;

use App\Services\ReportePdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    public function __construct(private ReportePdf $reportePdf)
    {
    }

    public function index(): View
    {
        return view('reportes.index');
    }

    public function prueba(): Response
    {
        return $this->reportePdf->generar('reportes.prueba', [], 'prueba-diseno-base.pdf');
    }
}
