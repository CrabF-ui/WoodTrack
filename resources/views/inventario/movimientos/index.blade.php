@extends('layouts.admin')

@section('title', 'Movimientos de inventario')
@section('page_title', 'Movimientos de inventario')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Historial de entradas y salidas</p>
        <a href="{{ route('inventario.movimientos.create') }}" class="btn btn-primary">Registrar movimiento</a>
    </div>

    <form method="GET" action="{{ route('inventario.movimientos.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-3"><label for="material_id" class="form-label">Material</label><select name="material_id" id="material_id" class="form-select"><option value="">Todos</option>@foreach ($materiales as $material)<option value="{{ $material->id }}" @selected(($filters['material_id'] ?? '') == $material->id)>{{ $material->nombre }}</option>@endforeach</select></div>
        <div class="col-md-2"><label for="tipo" class="form-label">Tipo</label><select name="tipo" id="tipo" class="form-select"><option value="">Todos</option><option value="entrada" @selected(($filters['tipo'] ?? '') === 'entrada')>Entrada</option><option value="salida" @selected(($filters['tipo'] ?? '') === 'salida')>Salida</option></select></div>
        <div class="col-md-2"><label for="origen" class="form-label">Origen</label><select name="origen" id="origen" class="form-select"><option value="">Todos</option><option value="manual" @selected(($filters['origen'] ?? '') === 'manual')>Manual</option><option value="pedido" @selected(($filters['origen'] ?? '') === 'pedido')>Pedido</option></select></div>
        <div class="col-md-2"><label for="estado" class="form-label">Mostrar</label><select name="estado" id="estado" class="form-select"><option value="activos" @selected($filters['estado'] === 'activos')>Activos</option><option value="anulados" @selected($filters['estado'] === 'anulados')>Anulados</option><option value="todos" @selected($filters['estado'] === 'todos')>Todos</option></select></div>
        <div class="col-md-2"><label for="q" class="form-label">Buscar</label><input type="search" name="q" id="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Material, motivo o usuario"></div>
        <div class="col-md-auto"><button type="submit" class="btn btn-outline-primary">Filtrar</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Historial</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Fecha</th><th>Material</th><th>Tipo</th><th class="text-end">Cantidad</th><th>Motivo</th><th>Registró</th><th>Origen / estado</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($movimientos as $movimiento)
                        <tr @class(['table-secondary' => $movimiento->trashed()])>
                            <td>{{ $movimiento->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $movimiento->material?->nombre ?? '—' }}</td>
                            <td><span class="badge {{ $movimiento->tipo === 'entrada' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($movimiento->tipo) }}</span></td>
                            <td class="text-end">{{ number_format((float) $movimiento->cantidad, 2) }} {{ $movimiento->material?->unidad_medida }}</td>
                            <td>{{ $movimiento->motivo ?: '—' }}</td>
                            <td>{{ $movimiento->registradoPor?->name ?? '—' }}</td>
                            <td>
                                @if ($movimiento->pedido_id !== null)
                                    <span>Automático (pedido #{{ $movimiento->pedido_id }})</span>
                                @else
                                    <span>Manual</span>
                                @endif
                                @if ($movimiento->trashed()) <span class="badge text-bg-secondary">Anulado</span> @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($movimiento->pedido_id === null)
                                    @if ($movimiento->trashed())
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('inventario.movimientos.restore', ['id' => $movimiento->id, ...$filters]) }}" data-action-method="POST" data-material-name="{{ $movimiento->material?->nombre ?? 'este material' }}" data-action-label="Restaurar">Restaurar</button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('inventario.movimientos.destroy', ['id' => $movimiento->id, ...$filters]) }}" data-action-method="DELETE" data-material-name="{{ $movimiento->material?->nombre ?? 'este material' }}" data-action-label="Anular">Anular</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay movimientos que coincidan con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($movimientos->hasPages())<div class="card-footer">{{ $movimientos->links() }}</div>@endif
    </div>

    <div class="modal fade" id="confirmarAccionModal" tabindex="-1" aria-labelledby="confirmarAccionTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="confirmarAccionFormulario" method="POST">@csrf<input type="hidden" name="_method" id="confirmarAccionMetodo" value=""><div class="modal-header"><h2 class="modal-title fs-5" id="confirmarAccionTitulo">Confirmar acción</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body" id="confirmarAccionMensaje"></div><div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="confirmarAccionBoton">Confirmar</button></div></form></div></div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('confirmarAccionFormulario');
        document.getElementById('confirmarAccionModal').addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const action = trigger.dataset.actionLabel;
            form.action = trigger.dataset.actionUrl;
            document.getElementById('confirmarAccionMetodo').value = trigger.dataset.actionMethod === 'DELETE' ? 'DELETE' : '';
            document.getElementById('confirmarAccionTitulo').textContent = `${action} movimiento`;
            document.getElementById('confirmarAccionMensaje').textContent = `¿${action} el movimiento de ${trigger.dataset.materialName}?`;
            document.getElementById('confirmarAccionBoton').textContent = action;
        });
    })();
</script>
@endpush
