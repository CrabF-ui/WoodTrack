@extends('layouts.admin')

@section('title', 'Pedidos')
@section('page_title', 'Pedidos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Gestión de pedidos de venta</p>
        <a href="{{ route('pedidos.create') }}" class="btn btn-primary">Crear pedido</a>
    </div>

    <form method="GET" action="{{ route('pedidos.index') }}" class="row g-2 align-items-end mb-3" id="filtros-pedidos">
        <div class="col-md-3">
            <label for="estado" class="form-label">Mostrar</label>
            <select name="estado" id="estado" class="form-select">
                <option value="activos" @selected($estado === 'activos')>Activos</option>
                <option value="eliminados" @selected($estado === 'eliminados')>Eliminados</option>
                <option value="todos" @selected($estado === 'todos')>Activos y eliminados</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="q" class="form-label">Buscar</label>
            <input type="search" name="q" id="q" class="form-control" value="{{ $search }}" placeholder="Cliente, número de pedido o estado">
        </div>
        <noscript>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-outline-primary">Buscar</button>
            </div>
        </noscript>
    </form>

    <div id="listado-pedidos">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Listado de pedidos</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Fecha de pedido</th>
                            <th>Fecha de entrega</th>
                            <th>Estado</th>
                            <th>Eliminación</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pedidos as $pedido)
                            <tr @class(['table-secondary' => $pedido->trashed()])>
                                <td>{{ $pedido->cliente?->nombre ?? '—' }}</td>
                                <td>{{ $pedido->fecha_pedido?->format('d/m/Y') }}</td>
                                <td>{{ $pedido->fecha_entrega?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if ($pedido->trashed())
                                        <span class="badge text-bg-secondary">Eliminado</span>
                                    @else
                                        @php
                                            $claseEstado = match ($pedido->estado) {
                                                'pendiente' => 'text-bg-warning',
                                                'en_proceso' => 'text-bg-primary',
                                                'completado' => 'text-bg-success',
                                                'cancelado' => 'text-bg-danger',
                                                default => 'text-bg-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $claseEstado }}">{{ Str::headline($pedido->estado) }}</span>
                                    @endif
                                </td>
                                <td>{{ $pedido->deleted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="text-end">${{ number_format((float) $pedido->total, 2) }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($pedido->trashed())
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('pedidos.restore', ['id' => $pedido->id, 'estado' => $estado, 'q' => $search]) }}" data-action-method="POST" data-client-name="{{ $pedido->cliente?->nombre ?? 'este cliente' }}" data-action-label="Restaurar">Restaurar</button>
                                    @else
                                        <a href="{{ route('pedidos.edit', $pedido->id) }}" class="btn btn-sm btn-outline-primary">Ver / editar</a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmarAccionModal" data-action-url="{{ route('pedidos.destroy', ['pedido' => $pedido->id, 'estado' => $estado, 'q' => $search]) }}" data-action-method="DELETE" data-client-name="{{ $pedido->cliente?->nombre ?? 'este cliente' }}" data-action-label="Eliminar">Eliminar</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay pedidos que coincidan con los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmarAccionModal" tabindex="-1" aria-labelledby="confirmarAccionTitulo" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
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
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const filterForm = document.getElementById('filtros-pedidos');
        const stateSelect = document.getElementById('estado');
        const searchInput = document.getElementById('q');
        const listing = document.getElementById('listado-pedidos');
        const confirmationForm = document.getElementById('confirmarAccionFormulario');
        const confirmationMethod = document.getElementById('confirmarAccionMetodo');
        let pendingRequest;
        let debounceTimer;

        const buildFilterUrl = () => {
            const url = new URL(filterForm.action, window.location.origin);
            url.searchParams.set('estado', stateSelect.value);
            if (searchInput.value.trim()) {
                url.searchParams.set('q', searchInput.value.trim());
            } else {
                url.searchParams.delete('q');
            }
            return url;
        };

        const loadListing = async (url, replaceUrl = true) => {
            pendingRequest?.abort();
            pendingRequest = new AbortController();

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: pendingRequest.signal,
                });
                if (!response.ok) {
                    return;
                }

                const documentResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
                const newListing = documentResponse.querySelector('#listado-pedidos');
                if (!newListing) {
                    return;
                }

                listing.innerHTML = newListing.innerHTML;
                if (replaceUrl) {
                    window.history.replaceState({}, '', url);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    filterForm.submit();
                }
            }
        };

        stateSelect.addEventListener('change', () => loadListing(buildFilterUrl()));
        searchInput.addEventListener('input', () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => loadListing(buildFilterUrl()), 300);
        });

        filterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            loadListing(buildFilterUrl());
        });

        listing.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-bs-target="#confirmarAccionModal"]');
            if (trigger) {
                const method = trigger.dataset.actionMethod;
                const action = trigger.dataset.actionLabel;
                confirmationForm.action = trigger.dataset.actionUrl;
                confirmationMethod.value = method === 'DELETE' ? 'DELETE' : '';
                document.getElementById('confirmarAccionTitulo').textContent = `${action} pedido`;
                document.getElementById('confirmarAccionMensaje').textContent = `¿${action} el pedido de ${trigger.dataset.clientName}?`;
                document.getElementById('confirmarAccionBoton').textContent = action;
                return;
            }

            const paginationLink = event.target.closest('.pagination a');
            if (paginationLink) {
                event.preventDefault();
                const url = new URL(paginationLink.href);
                stateSelect.value = url.searchParams.get('estado') || stateSelect.value;
                searchInput.value = url.searchParams.get('q') || searchInput.value;
                loadListing(url);
            }
        });
    })();
</script>
@endpush
