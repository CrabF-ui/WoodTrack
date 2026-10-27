@extends('layouts.admin')

@section('title', 'Registrar movimiento')
@section('page_title', 'Registrar movimiento')

@section('content')
    <div class="card">
        <div class="card-header"><h2 class="card-title">Entrada o salida manual</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventario.movimientos.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label for="material_id" class="form-label">Material</label>
                    <select name="material_id" id="material_id" class="form-select @error('material_id') is-invalid @enderror" required>
                        <option value="">Selecciona un material</option>
                        @foreach ($materiales as $material)
                            <option value="{{ $material->id }}" data-stock="{{ number_format((float) $material->stock_actual, 2, '.', '') }}" data-unidad="{{ $material->unidad_medida }}" @selected(old('material_id') == $material->id)>{{ $material->nombre }} ({{ ucfirst($material->tipo) }})</option>
                        @endforeach
                    </select>
                    @error('material_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" id="stock-material">Stock actual: selecciona un material.</div>
                </div>
                <div class="col-md-3"><label for="tipo" class="form-label">Tipo de movimiento</label><select name="tipo" id="tipo" class="form-select @error('tipo') is-invalid @enderror" required><option value="">Selecciona</option><option value="entrada" @selected(old('tipo') === 'entrada')>Entrada</option><option value="salida" @selected(old('tipo') === 'salida')>Salida</option></select>@error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label for="cantidad" class="form-label">Cantidad</label><input type="number" name="cantidad" id="cantidad" class="form-control @error('cantidad') is-invalid @enderror" min="0.01" max="99999999.99" step="0.01" value="{{ old('cantidad') }}" required>@error('cantidad')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label for="fecha" class="form-label">Fecha</label><input type="date" name="fecha" id="fecha" class="form-control @error('fecha') is-invalid @enderror" max="{{ now()->toDateString() }}" value="{{ old('fecha', now()->toDateString()) }}">@error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label for="motivo" class="form-label">Motivo (opcional)</label><input type="text" name="motivo" id="motivo" class="form-control @error('motivo') is-invalid @enderror" maxlength="255" value="{{ old('motivo') }}">@error('motivo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 d-flex gap-2"><button type="submit" class="btn btn-primary">Guardar movimiento</button><a href="{{ $cancelarUrl }}" class="btn btn-outline-secondary">Cancelar</a></div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const materialSelect = document.getElementById('material_id');
        const stockLabel = document.getElementById('stock-material');
        const updateStock = () => {
            const selected = materialSelect.selectedOptions[0];
            stockLabel.textContent = selected?.dataset.stock
                ? `Stock actual: ${selected.dataset.stock} ${selected.dataset.unidad}`
                : 'Stock actual: selecciona un material.';
        };
        materialSelect.addEventListener('change', updateStock);
        updateStock();
    })();
</script>
@endpush
