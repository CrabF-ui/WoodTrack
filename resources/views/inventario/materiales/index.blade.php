@extends('layouts.admin')

@section('title', 'Catálogo de materiales')
@section('page_title', 'Catálogo de materiales')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('inventario.index') }}" class="btn btn-outline-primary">Volver al inventario</a>
        <a href="{{ route('inventario.materiales.create') }}" class="btn btn-primary">Nuevo material</a>
    </div>

    <form method="GET" action="{{ route('inventario.materiales.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-4">
            <label for="estado" class="form-label">Mostrar</label>
            <select name="estado" id="estado" class="form-select">
                <option value="activos" @selected($estado === 'activos')>Activos</option>
                <option value="eliminados" @selected($estado === 'eliminados')>Dados de baja</option>
            </select>
        </div>
        <div class="col-md-auto"><button type="submit" class="btn btn-outline-primary">Filtrar</button></div>
    </form>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Materiales</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Nombre</th><th>Tipo</th><th>Unidad</th><th class="text-end">Stock actual</th><th class="text-end">Stock mínimo</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($materiales as $material)
                        <tr @class(['table-secondary' => $material->trashed()])>
                            <td>{{ $material->nombre }}</td>
                            <td>{{ ucfirst($material->tipo) }}</td>
                            <td>{{ $material->unidad_medida }}</td>
                            <td class="text-end">{{ number_format((float) $material->stock_actual, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $material->stock_minimo, 2) }}</td>
                            <td class="text-end text-nowrap">
                                @if ($material->trashed())
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#confirmarMaterial{{ $material->id }}">Restaurar</button>
                                @else
                                    <a href="{{ route('inventario.materiales.edit', $material->id) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmarMaterial{{ $material->id }}">Dar de baja</button>
                                @endif
                                <div class="modal fade text-start" id="confirmarMaterial{{ $material->id }}" tabindex="-1" aria-labelledby="confirmarMaterialTitulo{{ $material->id }}" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                                        <form method="POST" action="{{ $material->trashed() ? route('inventario.materiales.restore', $material->id) : route('inventario.materiales.destroy', $material->id) }}">
                                            @csrf
                                            @unless ($material->trashed()) @method('DELETE') @endunless
                                            <div class="modal-header"><h2 class="modal-title fs-5" id="confirmarMaterialTitulo{{ $material->id }}">{{ $material->trashed() ? 'Restaurar material' : 'Dar de baja material' }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                                            <div class="modal-body">¿{{ $material->trashed() ? 'Restaurar' : 'Dar de baja' }} el material {{ $material->nombre }}?</div>
                                            <div class="modal-footer"><button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">{{ $material->trashed() ? 'Restaurar' : 'Dar de baja' }}</button></div>
                                        </form>
                                    </div></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay materiales en el estado seleccionado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($materiales->hasPages())
            <div class="card-footer">{{ $materiales->links() }}</div>
        @endif
    </div>

@endsection
