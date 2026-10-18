@php
    $detallesIniciales = old('detalles', isset($pedido) ? $pedido->detalles->map(fn ($detalle) => [
        'producto' => $detalle->producto,
        'cantidad' => $detalle->cantidad,
        'precio_unitario' => $detalle->precio_unitario,
    ])->all() : [['producto' => '', 'cantidad' => 1, 'precio_unitario' => '']]);

    $herrajesIniciales = old('herrajes', isset($pedido) ? $pedido->herrajes->map(fn ($herraje) => [
        'material_id' => $herraje->material_id,
        'cantidad' => $herraje->cantidad,
    ])->all() : []);

    $formaPago = old('forma_pago', $pedido->forma_pago ?? 'Liquidado');
@endphp

<form action="{{ $action }}" method="POST">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="card mb-4">
        <div class="card-header"><h2 class="card-title">Datos del cliente</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="cliente_id" class="form-label">Cliente</label>
                    <select class="form-select" id="cliente_id" name="cliente_id" required>
                        <option value="">Selecciona un cliente</option>
                        @foreach ($clientes as $cliente)
                            <option value="{{ $cliente->id }}" data-telefono="{{ $cliente->telefono }}" @selected(old('cliente_id', $pedido->cliente_id ?? '') == $cliente->id)>{{ $cliente->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="form-text mb-0" id="cliente-telefono">Teléfono: —</p>
                    @error('cliente_id') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="direccion" class="form-label">Dirección</label>
                    <input class="form-control" type="text" id="direccion" name="direccion" value="{{ old('direccion', $pedido->direccion ?? '') }}" maxlength="255" required>
                    @error('direccion') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h2 class="card-title">Descripción del mueble</h2></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado" required>
                        @foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En proceso', 'completado' => 'Completado', 'cancelado' => 'Cancelado'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(old('estado', $pedido->estado ?? 'pendiente') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('estado') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="fecha_pedido" class="form-label">Fecha de levantamiento</label>
                    <input class="form-control" type="date" id="fecha_pedido" name="fecha_pedido" value="{{ old('fecha_pedido', isset($pedido) ? $pedido->fecha_pedido?->format('Y-m-d') : '') }}" required>
                    @error('fecha_pedido') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="fecha_entrega" class="form-label">Fecha de entrega</label>
                    <input class="form-control" type="date" id="fecha_entrega" name="fecha_entrega" value="{{ old('fecha_entrega', isset($pedido) ? $pedido->fecha_entrega?->format('Y-m-d') : '') }}" required>
                    @error('fecha_entrega') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row g-2 text-muted small fw-semibold mb-2 d-none d-md-flex">
                <div class="col-md-5">Tipo de mueble / producto</div><div class="col-md-2">Cantidad</div><div class="col-md-3">Precio unitario</div><div class="col-md-2"></div>
            </div>
            <div id="detalles-container">
                @foreach ($detallesIniciales as $indice => $detalle)
                    <div class="row g-2 align-items-end detalle-row mb-2">
                        <div class="col-md-5">
                            <label class="form-label d-md-none">Tipo de mueble / producto</label>
                            <input class="form-control" type="text" name="detalles[{{ $indice }}][producto]" value="{{ $detalle['producto'] ?? '' }}" placeholder="Ej. Clóset, cocina, escritorio" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label d-md-none">Cantidad</label>
                            <input class="form-control" type="number" min="1" name="detalles[{{ $indice }}][cantidad]" value="{{ $detalle['cantidad'] ?? 1 }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-md-none">Precio unitario</label>
                            <input class="form-control" type="number" min="0" step="0.01" name="detalles[{{ $indice }}][precio_unitario]" value="{{ $detalle['precio_unitario'] ?? '' }}" placeholder="0.00" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="button" class="btn btn-outline-danger quitar-detalle">Quitar</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="agregar-detalle">Agregar mueble/producto</button>
            @error('detalles') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h2 class="card-title">Especificaciones técnicas y medidas</h2></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach (['alto' => 'Alto', 'ancho' => 'Ancho', 'profundidad' => 'Profundidad', 'material_principal' => 'Material principal', 'acabado_color' => 'Acabado / color'] as $campo => $etiqueta)
                    <div class="col-md-{{ in_array($campo, ['material_principal', 'acabado_color']) ? '6' : '4' }}">
                        <label for="{{ $campo }}" class="form-label">{{ $etiqueta }}</label>
                        <input class="form-control" type="text" id="{{ $campo }}" name="{{ $campo }}" value="{{ old($campo, $pedido->$campo ?? '') }}" maxlength="255">
                        @error($campo) <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title">Herrajes utilizados</h2>
            <button type="button" class="btn btn-sm btn-outline-primary" id="agregar-herraje">Agregar herraje</button>
        </div>
        <div class="card-body">
            <datalist id="herrajes-disponibles">
                @foreach ($herrajesDisponibles as $material)
                    <option value="{{ $material->nombre }}" data-material-id="{{ $material->id }}"></option>
                @endforeach
            </datalist>
            <div class="row g-2 text-muted small fw-semibold mb-2 d-none d-md-flex">
                <div class="col-md-8">Material / herraje</div><div class="col-md-3">Cantidad</div><div class="col-md-1"></div>
            </div>
            <div id="herrajes-container">
                @foreach ($herrajesIniciales as $indice => $herraje)
                    @php
                        $materialHerraje = $herrajesDisponibles->firstWhere('id', $herraje['material_id'] ?? null);
                    @endphp
                    <div class="row g-2 align-items-end herraje-row mb-2">
                        <div class="col-md-8">
                            <label class="form-label d-md-none">Material / herraje</label>
                            <input class="form-control herraje-nombre" type="text" list="herrajes-disponibles" value="{{ $materialHerraje?->nombre ?? '' }}" placeholder="Busca un herraje" autocomplete="off">
                            <input class="herraje-material-id" type="hidden" name="herrajes[{{ $indice }}][material_id]" value="{{ $herraje['material_id'] ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-md-none">Cantidad</label>
                            <input class="form-control" type="number" name="herrajes[{{ $indice }}][cantidad]" value="{{ $herraje['cantidad'] ?? '' }}" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger quitar-herraje">Quitar</button></div>
                    </div>
                @endforeach
            </div>
            @error('herrajes') <div class="text-danger small">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h2 class="card-title">Condición de pago</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="total" class="form-label">Total</label>
                    <input class="form-control" type="number" id="total" name="total" value="{{ old('total', $pedido->total ?? '') }}" min="0" step="0.01" required>
                    @error('total') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label for="forma_pago" class="form-label">Forma de pago</label>
                    <select class="form-select" id="forma_pago" name="forma_pago" required>
                        <option value="Liquidado" @selected($formaPago === 'Liquidado')>Liquidado</option>
                        <option value="Anticipo" @selected($formaPago === 'Anticipo')>Anticipo</option>
                    </select>
                    @error('forma_pago') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row g-3 mt-0" id="datos-anticipo" @if ($formaPago !== 'Anticipo') hidden @endif>
                <div class="col-md-6">
                    <label for="monto_anticipo" class="form-label">Monto del anticipo</label>
                    <input class="form-control" type="number" id="monto_anticipo" name="monto_anticipo" value="{{ old('monto_anticipo', $pedido->monto_anticipo ?? '') }}" min="0.01" step="0.01">
                    @error('monto_anticipo') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label for="fecha_anticipo" class="form-label">Fecha del anticipo</label>
                    <input class="form-control" type="date" id="fecha_anticipo" name="fecha_anticipo" value="{{ old('fecha_anticipo', isset($pedido) ? optional($pedido->fecha_anticipo)->format('Y-m-d') : '') }}">
                    @error('fecha_anticipo') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ route('pedidos.index') }}" class="btn btn-outline-primary">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>

@push('scripts')
<script>
    (() => {
        const clienteSelect = document.getElementById('cliente_id');
        const clienteTelefono = document.getElementById('cliente-telefono');
        const actualizarTelefonoCliente = () => {
            const telefono = clienteSelect.selectedOptions[0]?.dataset.telefono;
            clienteTelefono.textContent = `Teléfono: ${telefono || '—'}`;
        };

        clienteSelect.addEventListener('change', actualizarTelefonoCliente);
        actualizarTelefonoCliente();

        const detallesContainer = document.getElementById('detalles-container');
        const herrajesContainer = document.getElementById('herrajes-container');
        let nextDetalleIndex = {{ empty($detallesIniciales) ? 0 : max(array_map('intval', array_keys($detallesIniciales))) + 1 }};
        let nextHerrajeIndex = {{ empty($herrajesIniciales) ? 0 : max(array_map('intval', array_keys($herrajesIniciales))) + 1 }};

        document.getElementById('agregar-detalle').addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end detalle-row mb-2';
            row.innerHTML = `
                <div class="col-md-5"><label class="form-label d-md-none">Tipo de mueble / producto</label><input class="form-control" type="text" name="detalles[${nextDetalleIndex}][producto]" placeholder="Ej. Clóset, cocina, escritorio" required></div>
                <div class="col-md-2"><label class="form-label d-md-none">Cantidad</label><input class="form-control" type="number" min="1" name="detalles[${nextDetalleIndex}][cantidad]" value="1" required></div>
                <div class="col-md-3"><label class="form-label d-md-none">Precio unitario</label><input class="form-control" type="number" min="0" step="0.01" name="detalles[${nextDetalleIndex}][precio_unitario]" placeholder="0.00" required></div>
                <div class="col-md-2 d-grid"><button type="button" class="btn btn-outline-danger quitar-detalle">Quitar</button></div>`;
            detallesContainer.appendChild(row);
            nextDetalleIndex++;
        });

        detallesContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('quitar-detalle') && detallesContainer.querySelectorAll('.detalle-row').length > 1) {
                event.target.closest('.detalle-row').remove();
            }
        });

        const actualizarMaterialId = (input) => {
            const row = input.closest('.herraje-row');
            const option = Array.from(document.querySelectorAll('#herrajes-disponibles option'))
                .find((item) => item.value === input.value);
            row.querySelector('.herraje-material-id').value = option?.dataset.materialId ?? '';
        };

        document.getElementById('agregar-herraje').addEventListener('click', () => {
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end herraje-row mb-2';
            row.innerHTML = `
                <div class="col-md-8"><label class="form-label d-md-none">Material / herraje</label><input class="form-control herraje-nombre" type="text" list="herrajes-disponibles" placeholder="Busca un herraje" autocomplete="off"><input class="herraje-material-id" type="hidden" name="herrajes[${nextHerrajeIndex}][material_id]"></div>
                <div class="col-md-3"><label class="form-label d-md-none">Cantidad</label><input class="form-control" type="number" name="herrajes[${nextHerrajeIndex}][cantidad]" min="0.01" step="0.01" required></div>
                <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger quitar-herraje">Quitar</button></div>`;
            herrajesContainer.appendChild(row);
            nextHerrajeIndex++;
        });

        herrajesContainer.addEventListener('input', (event) => {
            if (event.target.classList.contains('herraje-nombre')) {
                actualizarMaterialId(event.target);
            }
        });

        herrajesContainer.addEventListener('change', (event) => {
            if (event.target.classList.contains('herraje-nombre')) {
                actualizarMaterialId(event.target);
            }
        });

        herrajesContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('quitar-herraje')) {
                event.target.closest('.herraje-row').remove();
            }
        });

        const formaPago = document.getElementById('forma_pago');
        const datosAnticipo = document.getElementById('datos-anticipo');
        const montoAnticipo = document.getElementById('monto_anticipo');
        const fechaAnticipo = document.getElementById('fecha_anticipo');

        const actualizarCamposAnticipo = () => {
            const requiereAnticipo = formaPago.value === 'Anticipo';
            datosAnticipo.hidden = !requiereAnticipo;
            montoAnticipo.required = requiereAnticipo;
            fechaAnticipo.required = requiereAnticipo;
        };

        formaPago.addEventListener('change', actualizarCamposAnticipo);
        actualizarCamposAnticipo();
    })();
</script>
@endpush
