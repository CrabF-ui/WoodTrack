<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    @if (isset($entrega))
        <div class="mb-3">
            <label class="form-label">Pedido</label>
            <div class="form-control-plaintext">#{{ $entrega->pedido_id }} — {{ $entrega->pedido->cliente->nombre ?? 'Cliente' }}</div>
        </div>
    @else
        <div class="mb-3">
            <label for="pedido_id" class="form-label">Pedido</label>
            <select name="pedido_id" id="pedido_id" class="form-select @error('pedido_id') is-invalid @enderror" required>
                <option value="">Selecciona un pedido</option>
                @foreach ($pedidos as $pedido)
                    <option value="{{ $pedido->id }}" @selected(old('pedido_id') == $pedido->id)>#{{ $pedido->id }} — {{ $pedido->cliente->nombre ?? 'Cliente' }}{{ $pedido->direccion ? ' / '.$pedido->direccion : '' }}</option>
                @endforeach
            </select>
            @error('pedido_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @if ($pedidos->isEmpty()) <div class="form-text">No hay pedidos asignables.</div> @endif
        </div>
    @endif

    <div class="mb-3">
        <label for="chofer_id" class="form-label">Chofer</label>
        <select name="chofer_id" id="chofer_id" class="form-select @error('chofer_id') is-invalid @enderror" required>
            <option value="">Selecciona un chofer</option>
            @foreach ($choferes as $chofer)
                <option value="{{ $chofer->id }}" @selected(old('chofer_id', $entrega->chofer_id ?? $choferPreseleccionado) == $chofer->id)>{{ $chofer->name }}</option>
            @endforeach
        </select>
        @error('chofer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="row g-3">
        <div class="col-md-6 mb-3">
            <label for="fecha_asignada" class="form-label">Fecha asignada</label>
            <input type="date" name="fecha_asignada" id="fecha_asignada" min="{{ now()->toDateString() }}" value="{{ old('fecha_asignada', isset($entrega) ? $entrega->fecha_asignada->format('Y-m-d') : now()->toDateString()) }}" class="form-control @error('fecha_asignada') is-invalid @enderror" required>
            @error('fecha_asignada') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label for="orden" class="form-label">Orden (opcional)</label>
            <input type="number" min="1" name="orden" id="orden" value="{{ old('orden', $entrega->orden ?? '') }}" class="form-control @error('orden') is-invalid @enderror">
            @error('orden') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="mb-3">
        <label for="notas" class="form-label">Notas (opcional)</label>
        <textarea name="notas" id="notas" maxlength="255" rows="3" class="form-control @error('notas') is-invalid @enderror">{{ old('notas', $entrega->notas ?? '') }}</textarea>
        @error('notas') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('entregas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success" @disabled(!isset($entrega) && ($pedidos->isEmpty() || $choferes->isEmpty()))>{{ $submitLabel }}</button>
    </div>
</form>
