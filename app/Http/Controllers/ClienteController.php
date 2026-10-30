<?php

namespace App\Http\Controllers;

use App\Exceptions\ClienteNombreDuplicadoException;
use App\Http\Requests\ClienteRequest;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function __construct(private ClienteRepositoryInterface $clientes)
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

        return view('clientes.index', [
            'clientes' => $this->clientes->getFiltered($estado, $search),
            'estado' => $estado,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('clientes.create', ['filters' => request()->only(['estado', 'q'])]);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $this->clientes->create($request->validated());

        return redirect()->route('clientes.index', $this->listingFilters($request))->with('success', 'Cliente creado correctamente.');
    }

    public function edit(int $cliente): View
    {
        return view('clientes.edit', [
            'cliente' => $this->clientes->findById($cliente),
            'filters' => request()->only(['estado', 'q']),
        ]);
    }

    public function update(ClienteRequest $request, int $cliente): RedirectResponse
    {
        $this->clientes->update($cliente, $request->validated());

        return redirect()->route('clientes.index', $this->listingFilters($request))->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Request $request, int $cliente): RedirectResponse
    {
        $this->clientes->delete($cliente);

        return redirect()->route('clientes.index', $this->listingFilters($request))->with('success', 'Cliente dado de baja correctamente.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        try {
            $this->clientes->restore($id);
        } catch (ClienteNombreDuplicadoException $exception) {
            return redirect()->route('clientes.index', $this->listingFilters($request))->with('error', $exception->getMessage());
        }

        return redirect()->route('clientes.index', $this->listingFilters($request))->with('success', 'Cliente restaurado correctamente.');
    }

    private function listingFilters(Request $request): array
    {
        return $request->only(['estado', 'q']);
    }
}
