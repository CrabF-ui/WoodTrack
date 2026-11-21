<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Services\ReportePdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    public function __construct(
        private ReportePdf $reportePdf,
        private PedidoRepositoryInterface $pedidos,
        private MaterialRepositoryInterface $materiales,
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
}
