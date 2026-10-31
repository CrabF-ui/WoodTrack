<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\InventarioRepositoryInterface;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private InventarioRepositoryInterface $inventario)
    {
    }

    public function index(): View
    {
        return view('dashboard', [
            'totalBajoStock' => $this->inventario->countBajoStock(),
        ]);
    }
}