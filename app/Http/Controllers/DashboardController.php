<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Repositories\Contracts\InventarioRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private InventarioRepositoryInterface $inventario,
        private PedidoRepositoryInterface $pedidos,
        private EntregaRepositoryInterface $entregas,
    ) {}

    public function index(): View
    {
        $esAdmin = auth()->user()->rol === User::ROL_ADMIN;

        $datos = [
            'totalBajoStock' => $this->inventario->countBajoStock(),
            'totalSinEntrega' => $esAdmin ? $this->pedidos->countCompletadosSinEntrega() : null,
            'totalSinHerrajes' => $esAdmin ? $this->pedidos->countCompletadosSinHerrajes() : null,
        ];

        if ($esAdmin) {
            $datos['entregasHoy'] = $this->entregas->contarEntregasDeHoy();
            $datos['entregasSemana'] = $this->entregas->contarEntregasUltimos7Dias();
            $datos['pedidosSemana'] = $this->pedidos->sumarTotalUltimos7Dias();
        }

        return view('dashboard', $datos);
    }
}
