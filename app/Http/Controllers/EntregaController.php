<?php

namespace App\Http\Controllers;

use App\Http\Requests\EntregaRequest;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EntregaController extends Controller
{
    public function __construct(private EntregaRepositoryInterface $entregas)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'chofer_id' => ['nullable', 'integer', 'exists:users,id'],
            'fecha' => ['nullable', 'date'],
        ]);

        return view('entregas.index', [
            'entregas' => $this->entregas->getFiltered(isset($filters['chofer_id']) ? (int) $filters['chofer_id'] : null, $filters['fecha'] ?? null),
            'choferes' => User::where('rol', 'chofer')->orderBy('name')->get(),
            'filtros' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();
        $requestedPedidoId = $request->query('pedido_id');
        $pedidoId = is_scalar($requestedPedidoId)
            ? filter_var($requestedPedidoId, FILTER_VALIDATE_INT)
            : false;
        $data['pedidoPreseleccionado'] = $pedidoId !== false && $data['pedidos']->contains('id', $pedidoId)
            ? $pedidoId
            : null;

        return view('entregas.create', $data);
    }

    public function store(EntregaRequest $request): RedirectResponse
    {
        $resultado = $this->entregas->create($request->validated());

        return redirect()->route('entregas.index')->with('success', $this->mensajeExito('Entrega asignada correctamente.', $resultado['recorridas']));
    }

    public function edit(int $entrega): View|RedirectResponse
    {
        $model = $this->entregas->findById($entrega);
        if ($model->estado !== Entrega::ESTADO_PENDIENTE) {
            return redirect()->route('entregas.index')->with('error', 'Solo se pueden editar entregas pendientes.');
        }

        return view('entregas.edit', array_merge($this->formData(), ['entrega' => $model]));
    }

    public function update(EntregaRequest $request, int $entrega): RedirectResponse
    {
        $model = $this->entregas->findById($entrega);
        if ($model->estado !== Entrega::ESTADO_PENDIENTE) {
            return redirect()->route('entregas.index')->with('error', 'Solo se pueden editar entregas pendientes.');
        }

        $data = $request->validated();
        unset($data['pedido_id']);
        $resultado = $this->entregas->update($entrega, $data);

        return redirect()->route('entregas.index')->with('success', $this->mensajeExito('Entrega actualizada correctamente.', $resultado['recorridas']));
    }

    private function mensajeExito(string $mensaje, int $recorridas): string
    {
        if ($recorridas === 0) {
            return $mensaje;
        }

        return $mensaje.' '.($recorridas === 1
            ? 'Se recorrió 1 entrega.'
            : "Se recorrieron {$recorridas} entregas.");
    }

    private function formData(): array
    {
        $pedidos = Pedido::with('cliente')
            ->where('estado', '!=', 'cancelado')
            ->whereDoesntHave('entregas', fn ($query) => $query->whereIn('estado', Entrega::ESTADOS_ACTIVOS))
            ->orderByDesc('fecha_pedido')
            ->get();
        $choferes = User::where('rol', 'chofer')->where('activo', true)->orderBy('name')->get();

        return [
            'pedidos' => $pedidos,
            'choferes' => $choferes,
            'choferPreseleccionado' => $choferes->count() === 1 ? $choferes->first()->id : null,
        ];
    }
}
