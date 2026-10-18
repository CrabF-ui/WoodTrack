<?php

namespace App\Http\Controllers;

use App\Exceptions\StockInsuficienteException;
use App\Http\Requests\PedidoRequest;
use App\Models\Cliente;
use App\Models\Material;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PedidoController extends Controller
{
    public function __construct(private PedidoRepositoryInterface $pedidos)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'estado' => ['sometimes', 'in:activos,eliminados,todos'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $estado = $validated['estado'] ?? 'activos';
        $search = $validated['q'] ?? null;

        return view('pedidos.index', [
            'pedidos' => $this->pedidos->getFiltered($estado, $search),
            'estado' => $estado,
            'search' => $search,
        ]);
    }

    public function trashed(): RedirectResponse
    {
        return redirect()->route('pedidos.index', ['estado' => 'eliminados']);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        try {
            $this->pedidos->restore($id);
        } catch (StockInsuficienteException $e) {
            return redirect()->route('pedidos.index', $this->listingFilters($request))->with('error', $e->getMessage());
        }

        return redirect()->route('pedidos.index', $this->listingFilters($request))->with('success', 'Pedido restaurado correctamente.');
    }

    public function create(): View
    {
        return view('pedidos.create', [
            'clientes' => Cliente::orderBy('nombre')->get(),
            'herrajesDisponibles' => Material::where('tipo', 'herraje')->orderBy('nombre')->get(),
        ]);
    }

    public function store(PedidoRequest $request): RedirectResponse
    {
        try {
            $this->pedidos->create($request->validated());
        } catch (StockInsuficienteException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('pedidos.index')->with('success', 'Pedido creado correctamente.');
    }

    public function edit(int $pedido): View
    {
        return view('pedidos.edit', [
            'pedido' => $this->pedidos->findById($pedido),
            'clientes' => Cliente::orderBy('nombre')->get(),
            'herrajesDisponibles' => Material::where('tipo', 'herraje')->orderBy('nombre')->get(),
        ]);
    }

    public function update(PedidoRequest $request, int $pedido): RedirectResponse
    {
        try {
            $this->pedidos->update($pedido, $request->validated());
        } catch (StockInsuficienteException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('pedidos.index')->with('success', 'Pedido actualizado correctamente.');
    }

    public function destroy(Request $request, int $pedido): RedirectResponse
    {
        $this->pedidos->delete($pedido);

        return redirect()->route('pedidos.index', $this->listingFilters($request))->with('success', 'Pedido eliminado correctamente.');
    }

    private function listingFilters(Request $request): array
    {
        return $request->only(['estado', 'q']);
    }
}
