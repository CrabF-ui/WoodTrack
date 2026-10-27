@extends('layouts.admin')

@section('title', 'Inventario')
@section('page_title', 'Inventario')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Existencias de materia prima</p>
        <div class="d-flex gap-2">
            <a href="{{ route('inventario.movimientos.index') }}" class="btn btn-outline-primary">Historial de movimientos</a>
            <a href="{{ route('inventario.movimientos.create') }}" class="btn btn-primary">Registrar movimiento</a>
        </div>
    </div>

    <form method="GET" action="{{ route('inventario.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
            <label for="tipo" class="form-label">Tipo de material</label>
            <select name="tipo" id="tipo" class="form-select">
                <option value="">Todos</option>
                @foreach (['madera' => 'Madera', 'herraje' => 'Herraje', 'acabado' => 'Acabado'] as $tipo => $etiqueta)
                    <option value="{{ $tipo }}" @selected(($filters['tipo'] ?? '') === $tipo)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="bajo_stock" class="form-label">Existencias</label>
            <select name="bajo_stock" id="bajo_stock" class="form-select">
                <option value="0" @selected(! ($filters['bajo_stock'] ?? false))>Todos los niveles</option>
                <option value="1" @selected($filters['bajo_stock'] ?? false)>Solo bajo stock</option>
            </select>
        </div>
        <div class="col-md-auto"><button type="submit" class="btn btn-outline-primary">Filtrar</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Materiales</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Material</th><th>Tipo</th><th>Unidad</th><th class="text-end">Stock actual</th><th class="text-end">Stock mínimo</th><th>Descuento</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($materiales as $material)
                        <tr>
                            <td>{{ $material->nombre }}</td>
                            <td>{{ ucfirst($material->tipo) }}</td>
                            <td>{{ $material->unidad_medida }}</td>
                            <td class="text-end">{{ number_format((float) $material->stock_actual, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $material->stock_minimo, 2) }}</td>
                            <td>{{ $material->tipo === 'herraje' ? 'Automático (pedido)' : 'Manual' }}</td>
                            <td>@if ((float) $material->stock_actual <= (float) $material->stock_minimo)<span class="badge text-bg-warning">Bajo stock</span>@else<span class="badge text-bg-success">Disponible</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No hay materiales que coincidan con los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
