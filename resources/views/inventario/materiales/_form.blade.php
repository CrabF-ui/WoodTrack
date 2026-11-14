<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card">
        <div class="card-header"><h2 class="card-title">Datos del material</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nombre" class="form-label">Nombre</label>
                    <input class="form-control @error('nombre') is-invalid @enderror" type="text" id="nombre" name="nombre" value="{{ old('nombre', $material->nombre ?? '') }}" maxlength="255" required>
                    @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select class="form-select @error('tipo') is-invalid @enderror" id="tipo" name="tipo" @disabled($tipoBloqueado ?? false) required>
                        <option value="">Selecciona</option>
                        @foreach (['madera' => 'Madera', 'herraje' => 'Herraje', 'acabado' => 'Acabado'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('tipo', $material->tipo ?? '') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @if ($tipoBloqueado ?? false)
                        <input type="hidden" name="tipo" value="{{ $material->tipo }}">
                        <div class="form-text">El tipo no puede cambiarse porque hay movimientos o pedidos asociados.</div>
                    @endif
                    @error('tipo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label for="unidad_medida" class="form-label">Unidad de medida</label>
                    <select class="form-select @error('unidad_medida') is-invalid @enderror" id="unidad_medida" name="unidad_medida" required>
                        <option value="">Selecciona</option>
                        @foreach (['pieza', 'pie', 'pulgada', 'cm', 'mm', 'litro', 'cubeta'] as $unidad)
                            <option value="{{ $unidad }}" @selected(old('unidad_medida', $material->unidad_medida ?? '') === $unidad)>{{ ucfirst($unidad) }}</option>
                        @endforeach
                    </select>
                    @error('unidad_medida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="stock_minimo" class="form-label">Stock mínimo</label>
                    <input class="form-control @error('stock_minimo') is-invalid @enderror" type="number" id="stock_minimo" name="stock_minimo" value="{{ old('stock_minimo', $material->stock_minimo ?? 0) }}" min="0" max="99999999.99" step="0.01" required>
                    @error('stock_minimo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                @if ($method === 'POST')
                    <div class="col-md-6">
                        <label for="stock_inicial" class="form-label">Stock inicial <span class="text-muted">(opcional)</span></label>
                        <input class="form-control @error('stock_inicial') is-invalid @enderror" type="number" id="stock_inicial" name="stock_inicial" value="{{ old('stock_inicial') }}" min="0" max="99999999.99" step="0.01">
                        @error('stock_inicial') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @else
                    <div class="col-md-6">
                        <label class="form-label">Stock actual</label>
                        <input class="form-control" value="{{ number_format((float) $material->stock_actual, 2) }}" disabled>
                        <div class="form-text">El stock se modifica mediante movimientos de inventario.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('inventario.materiales.index') }}" class="btn btn-outline-primary">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>
