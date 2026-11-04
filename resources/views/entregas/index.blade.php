@extends('layouts.admin')

@section('title', 'Entregas')
@section('page_title', 'Entregas')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Asignación y seguimiento de entregas</p>
        <a href="{{ route('entregas.create') }}" class="btn btn-primary">Asignar entrega</a>
    </div>

    <form method="GET" action="{{ route('entregas.index') }}" class="row g-3 align-items-end mb-3">
        <div class="col-md-4">
            <label for="chofer_id" class="form-label">Chofer</label>
            <select name="chofer_id" id="chofer_id" class="form-select">
                <option value="">Todos</option>
                @foreach ($choferes as $chofer)
                    <option value="{{ $chofer->id }}" @selected(($filtros['chofer_id'] ?? '') == $chofer->id)>{{ $chofer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="fecha" class="form-label">Fecha asignada</label>
            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ $filtros['fecha'] ?? '' }}">
        </div>
        <div class="col-md-4 d-flex gap-2">
            <button class="btn btn-outline-primary" type="submit">Filtrar</button>
            <a class="btn btn-outline-secondary" href="{{ route('entregas.index') }}">Limpiar</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Listado de entregas</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Pedido</th><th>Cliente</th><th>Dirección</th><th>Chofer</th><th>Fecha asignada</th><th>Orden</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($entregas as $entrega)
                        <tr>
                            <td>#{{ $entrega->pedido_id }}</td>
                            <td>{{ $entrega->pedido->cliente->nombre ?? '—' }}</td>
                            <td>{{ $entrega->pedido->direccion ?: '—' }}</td>
                            <td>{{ $entrega->chofer->name }}</td>
                            <td>{{ $entrega->fecha_asignada->format('d/m/Y') }}</td>
                            <td>{{ $entrega->orden ?? '—' }}</td>
                            <td><span class="badge {{ $entrega->estado === 'pendiente' ? 'text-bg-warning' : ($entrega->estado === 'en_camino' ? 'text-bg-info' : ($entrega->estado === 'entregada' ? 'text-bg-success' : 'text-bg-danger')) }}">{{ Str::headline($entrega->estado) }}</span></td>
                            <td>@if ($entrega->estado === \App\Models\Entrega::ESTADO_PENDIENTE)<a href="{{ route('entregas.edit', $entrega) }}" class="btn btn-sm btn-outline-primary">Editar</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay entregas para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entregas->hasPages())
            <div class="card-footer">{{ $entregas->links() }}</div>
        @endif
    </div>
@endsection
