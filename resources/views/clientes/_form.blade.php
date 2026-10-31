<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="estado" value="{{ old('estado', $filters['estado'] ?? '') }}">
    <input type="hidden" name="q" value="{{ old('q', $filters['q'] ?? '') }}">

    <div class="card">
        <div class="card-header"><h2 class="card-title">Datos del cliente</h2></div>
        <div class="card-body">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre</label>
                <input class="form-control @error('nombre') is-invalid @enderror" type="text" id="nombre" name="nombre" value="{{ old('nombre', $cliente->nombre ?? '') }}" maxlength="120" required>
                @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="telefono" class="form-label">Teléfono <span class="text-muted">(opcional)</span></label>
                <input class="form-control @error('telefono') is-invalid @enderror" type="text" id="telefono" name="telefono" value="{{ old('telefono', $cliente->telefono ?? '') }}" maxlength="30">
                @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="{{ route('clientes.index', $filters ?? []) }}" class="btn btn-outline-primary">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>
