<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProblemasEntregasRequest;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Services\EvidenciaEntregaService;
use App\Services\ReportePdf;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    public function __construct(
        private ReportePdf $reportePdf,
        private PedidoRepositoryInterface $pedidos,
        private MaterialRepositoryInterface $materiales,
        private EntregaRepositoryInterface $entregas,
        private EvidenciaEntregaService $evidencias,
    ) {}

    public function index(): View
    {
        $hoy = Carbon::now(config('reportes.zona_horaria'));

        return view('reportes.index', [
            'periodoPredeterminado' => [
                'desde' => $hoy->copy()->startOfMonth()->toDateString(),
                'hasta' => $hoy->copy()->endOfMonth()->toDateString(),
            ],
        ]);
    }

    public function prueba(): Response
    {
        return $this->reportePdf->generar('reportes.prueba', [], 'prueba-diseno-base.pdf');
    }

    public function notaPedido(int $pedido): Response
    {
        $pedido = $this->pedidos->findById($pedido);

        return $this->reportePdf->generar(
            'reportes.nota-pedido',
            ['pedido' => $pedido],
            "nota-pedido-{$pedido->id}.pdf",
        );
    }

    public function listaCompras(): Response
    {
        return $this->reportePdf->generar(
            'reportes.lista-compras',
            ['materiales' => $this->materiales->getBajoStock()],
            'lista-compras.pdf',
        );
    }

    public function comprobanteEntrega(int $entrega): Response
    {
        $entrega = $this->entregas->findWithEvidencia($entrega);
        abort_unless($entrega->evidenciaCompleta(), 404);

        $firma = $entrega->sin_firma ? null : $this->evidencias->firmaComoDataUri($entrega->firma_path);
        $fotos = $entrega->fotos
            ->sortBy('id')
            ->take(EvidenciaEntregaService::MAXIMO_FOTOS)
            ->map(fn ($foto) => $this->evidencias->fotoComoDataUri($foto->ruta));

        $response = $this->reportePdf->generar(
            'reportes.comprobante-entrega',
            compact('entrega', 'firma', 'fotos'),
            "comprobante-entrega-{$entrega->id}.pdf",
        );
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function problemasEntregas(ProblemasEntregasRequest $request): Response
    {
        $periodo = $request->validated();
        $zonaReportes = config('reportes.zona_horaria');
        $zonaAplicacion = config('app.timezone');
        $desde = Carbon::createFromFormat('!Y-m-d', $periodo['desde'], $zonaReportes);
        $hasta = Carbon::createFromFormat('!Y-m-d', $periodo['hasta'], $zonaReportes)->endOfDay();
        $incidencias = $this->entregas->getIncidenciasConSituacion(
            $desde->copy()->setTimezone($zonaAplicacion),
            $hasta->copy()->setTimezone($zonaAplicacion),
        );
        $resumen = [
            'total' => $incidencias->count(),
            'sinAtender' => $incidencias->where('situacion', 'Sin atender')->count(),
            'reasignadas' => $incidencias->where('situacion', 'Reasignada')->count(),
            'resueltas' => $incidencias->where('situacion', 'Resuelta')->count(),
        ];

        return $this->reportePdf->generar(
            'reportes.problemas-entregas',
            compact('incidencias', 'resumen', 'desde', 'hasta'),
            'problemas-entregas.pdf',
        );
    }
}
