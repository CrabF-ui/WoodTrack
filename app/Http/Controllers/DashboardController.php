<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\InventarioRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private InventarioRepositoryInterface $inventario,
        private PedidoRepositoryInterface $pedidos,
    )
    {
    }

    public function index(): View
    {
        $esAdmin = auth()->user()->rol === User::ROL_ADMIN;

        return view('dashboard', [
            'totalBajoStock' => $this->inventario->countBajoStock(),
            'totalSinEntrega' => $esAdmin ? $this->pedidos->countCompletadosSinEntrega() : null,
        ]);
    }
}
