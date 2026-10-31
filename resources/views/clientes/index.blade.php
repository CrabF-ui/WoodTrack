@extends('layouts.admin')

@section('title', 'Clientes')
@section('page_title', 'Clientes')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Registro de clientes</p>
        <a href="{{ route('clientes.create', ['estado' => $estado, 'q' => $search]) }}" class="btn btn-primary">Crear cliente</a>
    </div>

    <form method="GET" action="{{ route('clientes.index') }}" class="row g-2 align-items-end mb-3" id="filtros-clientes">
        <div class="col-md-3">
            <label for="estado" class="form-label">Mostrar</label>
            <select name="estado" id="estado" class="form-select">
                <option value="activos" @selected($estado === 'activos')>Activos</option>
                <option value="eliminados" @selected($estado === 'eliminados')>Eliminados</option>
                <option value="todos" @selected($estado === 'todos')>Activos y eliminados</option>
            </select>
        </div>
        <div class="col-md-7">
            <label for="q" class="form-label">Buscar por nombre</label>
            <input type="search" name="q" id="q" class="form-control" value="{{ $search }}" placeholder="Nombre del cliente">
        </div>
        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Buscar</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Listado de clientes</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Nombre</th><th>Teléfono</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        <tr @class(['table-secondary' => $cliente->trashed()])>
                            <td>{{ $cliente->nombre }}</td>
                            <td>{{ $cliente->telefono ?: '—' }}</td>
                            <td>
                                @if ($cliente->trashed())
                                    <span class="badge text-bg-secondary">Eliminado</span>
                                @else
                                    <span class="badge text-bg-success">Activo</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($cliente->trashed())
                                    @if ($estado === 'eliminados')
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('clientes.restore', ['id' => $cliente->id, 'estado' => $estado, 'q' => $search]) }}" data-action-method="POST" data-client-name="{{ $cliente->nombre }}" data-action-label="Restaurar">Restaurar</button>
                                    @endif
                                @else
                                    <a href="{{ route('clientes.edit', ['cliente' => $cliente->id, 'estado' => $estado, 'q' => $search]) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('clientes.destroy', ['cliente' => $cliente->id, 'estado' => $estado, 'q' => $search]) }}" data-action-method="DELETE" data-client-name="{{ $cliente->nombre }}" data-action-label="Dar de baja">Dar de baja</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No hay clientes que coincidan con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($clientes->hasPages())
            <div class="card-footer">{{ $clientes->links() }}</div>
        @endif
    </div>

    <div class="modal fade" id="confirmarAccionModal" tabindex="-1" aria-labelledby="confirmarAccionTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="confirmarAccionFormulario" method="POST">
                @csrf
                <input type="hidden" name="_method" id="confirmarAccionMetodo" value="">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="confirmarAccionTitulo">Confirmar acción</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" id="confirmarAccionMensaje"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="confirmarAccionBoton">Confirmar</button>
                </div>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('filtros-clientes');
        document.getElementById('estado').addEventListener('change', () => form.submit());

        const confirmationForm = document.getElementById('confirmarAccionFormulario');
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-bs-target="#confirmarAccionModal"]');
            if (!trigger) return;

            const action = trigger.dataset.actionLabel;
            confirmationForm.action = trigger.dataset.actionUrl;
            document.getElementById('confirmarAccionMetodo').value = trigger.dataset.actionMethod === 'DELETE' ? 'DELETE' : '';
            document.getElementById('confirmarAccionTitulo').textContent = `${action} cliente`;
            document.getElementById('confirmarAccionMensaje').textContent = `¿${action} a ${trigger.dataset.clientName}?`;
            document.getElementById('confirmarAccionBoton').textContent = action;
        });
    })();
</script>
@endpush
