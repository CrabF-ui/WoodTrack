<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Services\EvidenciaEntregaService;
use App\Services\ReportePdf;
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
        return view('reportes.index');
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
}
