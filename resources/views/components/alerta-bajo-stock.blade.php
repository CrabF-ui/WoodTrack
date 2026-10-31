@props(['total', 'filtrado' => false])

@if ($total > 0)
    <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
        <strong>⚠️ {{ $total }} {{ $total === 1 ? 'material' : 'materiales' }} en bajo stock</strong>

        @if ($filtrado)
            <a href="{{ route('inventario.index') }}" class="alert-link">Ver todos</a>
        @else
            <a href="{{ route('inventario.index', ['bajo_stock' => 1]) }}" class="alert-link">Ver materiales</a>
        @endif
    </div>
@endif