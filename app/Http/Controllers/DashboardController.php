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
            $ventas = $this->pedidos->getVentasMes(now());
            $comparacion = $ventas['comparacion'];

            $datos['indicadoresMes'] = [
                'total' => $ventas['total'],
                'pedidos' => $ventas['pedidos'],
                'variacion' => $comparacion['variacion'],
                'totalAnterior' => $comparacion['totalAnterior'],
                'productoPrincipal' => $ventas['topProductos']->first(),
                'ticketPromedio' => $ventas['ticketPromedio'],
                'ticketAnterior' => $comparacion['pedidosAnterior'] > 0
                    ? $comparacion['totalAnterior'] / $comparacion['pedidosAnterior']
                    : null,
                'clientePrincipal' => $ventas['topClientes']->first(),
                'topProductos' => $ventas['topProductos'],
                'topClientes' => $ventas['topClientes'],
            ];
            $datos['entregasHoy'] = $this->entregas->contarEntregasDeHoy();
            $datos['entregasSemana'] = $this->entregas->contarEntregasUltimos7Dias();
            $datos['pedidosSemana'] = $this->pedidos->sumarTotalUltimos7Dias();
        }

        return view('dashboard', $datos);
    }
}
