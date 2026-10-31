<?php

namespace App\Http\Controllers;

use App\Exceptions\StockInsuficienteException;
use App\Http\Requests\MovimientoInventarioRequest;
use App\Models\Material;
use App\Repositories\Contracts\InventarioRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovimientoInventarioController extends Controller
{
    public function __construct(private InventarioRepositoryInterface $inventario)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tipo' => ['nullable', 'in:madera,herraje,acabado'],
            'bajo_stock' => ['nullable', 'boolean'],
        ]);

        return view('inventario.index', [
            'materiales' => $this->inventario->getAllMateriales((bool) ($filters['bajo_stock'] ?? false))
                ->when(isset($filters['tipo']), fn ($materiales) => $materiales->where('tipo', $filters['tipo'])->values()),
            'filters' => $filters,
            'totalBajoStock' => $this->inventario->countBajoStock(), // total real, sin importar los filtros
        ]);
    }

    public function movimientos(Request $request): View
    {
        $filters = $request->validate([
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'tipo' => ['nullable', 'in:entrada,salida'],
            'origen' => ['nullable', 'in:manual,pedido'],
            'estado' => ['nullable', 'in:activos,anulados,todos'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $filters['estado'] ??= 'activos';

        return view('inventario.movimientos.index', [
            'movimientos' => $this->inventario->getFilteredMovimientos($filters),
            'filters' => $filters,
            'materiales' => Material::orderBy('nombre')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $origen = in_array($request->query('origen'), ['inventario', 'historial'], true)
            ? $request->query('origen')
            : 'historial';

        return view('inventario.movimientos.create', [
            'materiales' => Material::orderBy('nombre')->get(),
            'cancelarUrl' => $origen === 'inventario'
                ? route('inventario.index')
                : route('inventario.movimientos.index'),
        ]);
    }

    public function store(MovimientoInventarioRequest $request): RedirectResponse
    {
        try {
            $this->inventario->registrarMovimiento([
                ...$request->validated(),
                'registrado_por' => $request->user()->id,
            ]);
        } catch (StockInsuficienteException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('inventario.movimientos.index')->with('success', 'Movimiento registrado correctamente.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        try {
            $this->inventario->deleteMovimiento($id);
        } catch (StockInsuficienteException $exception) {
            return redirect()->route('inventario.movimientos.index', $this->listingFilters($request))->with('error', $exception->getMessage());
        }

        return redirect()->route('inventario.movimientos.index', $this->listingFilters($request))->with('success', 'Movimiento anulado correctamente.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        try {
            $this->inventario->restoreMovimiento($id);
        } catch (StockInsuficienteException $exception) {
            return redirect()->route('inventario.movimientos.index', $this->listingFilters($request))->with('error', $exception->getMessage());
        }

        return redirect()->route('inventario.movimientos.index', $this->listingFilters($request))->with('success', 'Movimiento restaurado correctamente.');
    }

    private function listingFilters(Request $request): array
    {
        return $request->only(['material_id', 'tipo', 'origen', 'estado', 'q']);
    }
}
