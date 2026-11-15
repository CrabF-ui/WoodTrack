<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialRequest;
use App\Models\Material;
use App\Repositories\Contracts\InventarioRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialController extends Controller
{
    public function __construct(private InventarioRepositoryInterface $inventario)
    {
    }

    public function index(Request $request): View
    {
        $validated = $request->validate(['estado' => ['sometimes', 'in:activos,eliminados']]);
        $estado = $validated['estado'] ?? 'activos';

        $materiales = Material::query()
            ->when($estado === 'eliminados', fn ($query) => $query->onlyTrashed())
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('inventario.materiales.index', compact('materiales', 'estado'));
    }

    public function create(): View
    {
        return view('inventario.materiales.create');
    }

    public function store(MaterialRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request): void {
            $material = $this->inventario->createMaterial([
                ...Arr::except($data, ['stock_inicial']),
                'stock_actual' => 0,
            ]);

            if ((float) ($data['stock_inicial'] ?? 0) > 0) {
                $this->inventario->registrarMovimiento([
                    'material_id' => $material->id,
                    'tipo' => 'entrada',
                    'cantidad' => $data['stock_inicial'],
                    'motivo' => 'Stock inicial',
                    'registrado_por' => $request->user()->id,
                    'fecha' => now()->toDateString(),
                ]);
            }
        });

        return redirect()->route('inventario.materiales.index')->with('success', 'Material creado correctamente.');
    }

    public function edit(int $material): View
    {
        $material = Material::withCount([
            'movimientos' => fn ($query) => $query->withTrashed(),
            'pedidoHerrajes',
        ])->findOrFail($material);

        return view('inventario.materiales.edit', [
            'material' => $material,
            'tipoBloqueado' => $material->movimientos_count > 0 || $material->pedido_herrajes_count > 0,
        ]);
    }

    public function update(MaterialRequest $request, int $material): RedirectResponse
    {
        $this->inventario->updateMaterial($material, Arr::except($request->validated(), ['stock_inicial']));

        return redirect()->route('inventario.materiales.index')->with('success', 'Material actualizado correctamente.');
    }

    public function destroy(int $material): RedirectResponse
    {
        $materialModel = Material::findOrFail($material);

        if ($materialModel->pedidoHerrajes()->exists()) {
            return redirect()->route('inventario.materiales.index')
                ->with('error', 'No se puede dar de baja el material porque está asociado a pedidos.');
        }

        $this->inventario->deleteMaterial($material);

        return redirect()->route('inventario.materiales.index')->with('success', 'Material dado de baja correctamente.');
    }

    public function restore(int $id): RedirectResponse
    {
        $material = Material::onlyTrashed()->findOrFail($id);
        $duplicado = Material::where('nombre', $material->nombre)->exists();

        if ($duplicado) {
            return redirect()->route('inventario.materiales.index', ['estado' => 'eliminados'])
                ->with('error', 'No se puede restaurar el material porque ya existe otro material activo con el mismo nombre.');
        }

        $this->inventario->restoreMaterial($id);

        return redirect()->route('inventario.materiales.index', ['estado' => 'eliminados'])->with('success', 'Material restaurado correctamente.');
    }
}
