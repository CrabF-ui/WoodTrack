@extends('layouts.admin')

@section('title', 'Usuarios')
@section('page_title', 'Usuarios')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Cuentas de acceso al sistema</p>
        <a href="{{ route('usuarios.create', request()->only(['estado', 'filtro_rol', 'q'])) }}" class="btn btn-primary">Crear usuario</a>
    </div>
    <form method="GET" action="{{ route('usuarios.index') }}" class="row g-2 align-items-end mb-3" id="filtros-usuarios">
        <div class="col-md-2"><label for="estado" class="form-label">Estado</label><select name="estado" id="estado" class="form-select"><option value="activos" @selected($estado === 'activos')>Activos</option><option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option><option value="todos" @selected($estado === 'todos')>Todos</option></select></div>
        <div class="col-md-3"><label for="filtro_rol" class="form-label">Rol</label><select name="filtro_rol" id="filtro_rol" class="form-select"><option value="">Todos los roles</option><option value="admin" @selected($filtro_rol === 'admin')>Administrador</option><option value="encargado_inventario" @selected($filtro_rol === 'encargado_inventario')>Encargado de inventario</option><option value="chofer" @selected($filtro_rol === 'chofer')>Chofer</option></select></div>
        <div class="col-md-5"><label for="q" class="form-label">Buscar por nombre o correo</label><input type="search" name="q" id="q" class="form-control" value="{{ $search }}" placeholder="Nombre o correo"></div>
        <div class="col-md-2 d-grid"><button type="submit" class="btn btn-outline-primary">Buscar</button></div>
    </form>
    <div class="card"><div class="card-header"><h2 class="card-title">Listado de usuarios</h2></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
        @forelse ($usuarios as $usuario)
            <tr @class(['table-secondary' => ! $usuario->activo])><td>{{ $usuario->name }}</td><td>{{ $usuario->email }}</td><td>{{ ['admin' => 'Administrador', 'encargado_inventario' => 'Encargado de inventario', 'chofer' => 'Chofer'][$usuario->rol] ?? $usuario->rol }}</td><td><span class="badge text-bg-{{ $usuario->activo ? 'success' : 'secondary' }}">{{ $usuario->activo ? 'Activo' : 'Inactivo' }}</span></td><td class="text-end text-nowrap"><a href="{{ route('usuarios.edit', ['usuario' => $usuario->id] + request()->only(['estado', 'filtro_rol', 'q'])) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                @if ($usuario->activo)
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('usuarios.desactivar', ['usuario' => $usuario->id] + request()->only(['estado', 'filtro_rol', 'q'])) }}" data-action-method="PATCH" data-client-name="{{ $usuario->name }}" data-action-label="Desactivar">Desactivar</button>
                @else
                    <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('usuarios.reactivar', ['usuario' => $usuario->id] + request()->only(['estado', 'filtro_rol', 'q'])) }}" data-action-method="PATCH" data-client-name="{{ $usuario->name }}" data-action-label="Reactivar">Reactivar</button>
                @endif
            </td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-4">No hay usuarios que coincidan con los filtros seleccionados.</td></tr> @endforelse
    </tbody></table></div>@if ($usuarios->hasPages())<div class="card-footer">{{ $usuarios->links() }}</div>@endif</div>

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
        const filtersForm = document.getElementById('filtros-usuarios');
        document.getElementById('estado').addEventListener('change', () => filtersForm.submit());

        const confirmationForm = document.getElementById('confirmarAccionFormulario');
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-bs-target="#confirmarAccionModal"]');
            if (!trigger) return;

            const action = trigger.dataset.actionLabel;
            confirmationForm.action = trigger.dataset.actionUrl;
            document.getElementById('confirmarAccionMetodo').value = trigger.dataset.actionMethod;
            document.getElementById('confirmarAccionTitulo').textContent = `${action} usuario`;
            const message = action === 'Desactivar'
                ? `¿Desactivar a ${trigger.dataset.clientName}? No podrá iniciar sesión, pero se conservará su historial.`
                : `¿Reactivar a ${trigger.dataset.clientName}? Podrá volver a iniciar sesión.`;
            document.getElementById('confirmarAccionMensaje').textContent = message;
            document.getElementById('confirmarAccionBoton').textContent = action;
        });
    })();
</script>
@endpush
