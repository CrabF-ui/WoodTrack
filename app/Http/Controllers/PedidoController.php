<?php

namespace App\Http\Controllers;

use App\Exceptions\StockInsuficienteException;
use App\Http\Requests\PedidoRequest;
use App\Models\Cliente;
use App\Models\Material;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PedidoController extends Controller
{
    public function __construct(private PedidoRepositoryInterface $pedidos)
    {
    }

    public function index(): View
    {
        return view('pedidos.index', [
            'pedidos' => $this->pedidos->getAll(),
        ]);
    }

    public function trashed(): View
    {
        return view('pedidos.papelera', [
            'pedidos' => $this->pedidos->getTrashed(),
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        try {
            $this->pedidos->restore($id);
        } catch (StockInsuficienteException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pedidos.trashed')->with('success', 'Pedido restaurado correctamente.');
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

    public function destroy(int $pedido): RedirectResponse
    {
        $this->pedidos->delete($pedido);

        return redirect()->route('pedidos.index')->with('success', 'Pedido eliminado correctamente.');
    }
}
