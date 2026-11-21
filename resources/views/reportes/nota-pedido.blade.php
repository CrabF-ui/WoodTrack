@extends('reportes.layout')

@section('titulo', 'Nota de pedido #'.$pedido->id)

@section('contenido')
    <style>
        .cancelado { border: 3px solid #b91c1c; color: #b91c1c; font-size: 22px; font-weight: bold; margin-bottom: 18px; padding: 10px; text-align: center; }
        .section-title { border-bottom: 1px solid #bbb; font-size: 14px; margin: 18px 0 8px; padding-bottom: 4px; }
        .data-table { border-collapse: collapse; width: 100%; }
        .data-table td { padding: 3px 8px 3px 0; vertical-align: top; width: 25%; }
        .label { color: #555; display: block; font-size: 9px; }
        .report-table tr { page-break-inside: avoid; }
        .report-table thead { display: table-header-group; }
        .payment-section { page-break-inside: avoid; }
        .money { text-align: right; white-space: nowrap; }
        .payment-summary { border-collapse: collapse; margin-left: auto; margin-top: 12px; width: 48%; }
        .payment-summary td { border-bottom: 1px solid #ddd; padding: 5px 6px; }
        .payment-summary td:last-child { text-align: right; white-space: nowrap; }
        .payment-summary .total td { border-top: 2px solid #777; font-size: 13px; font-weight: bold; }
    </style>

    @if ($pedido->estado === 'cancelado')
        <div class="cancelado">CANCELADO</div>
    @endif

    <h2 class="section-title">Datos del pedido</h2>
    <table class="data-table">
        <tr>
            <td><span class="label">Folio</span>#{{ $pedido->id }}</td>
            <td><span class="label">Fecha del pedido</span>{{ $pedido->fecha_pedido?->format('d/m/Y') ?? '—' }}</td>
            <td><span class="label">Fecha estimada de entrega</span>{{ $pedido->fecha_entrega?->format('d/m/Y') ?? '—' }}</td>
            <td><span class="label">Estado</span>{{ $pedido->estado_label }}</td>
        </tr>
    </table>

    <h2 class="section-title">Cliente</h2>
    <table class="data-table">
        <tr>
            <td><span class="label">Nombre</span>{{ $pedido->cliente?->nombre ?? '—' }}</td>
            <td><span class="label">Teléfono</span>{{ $pedido->cliente?->telefono ?: '—' }}</td>
            <td colspan="2"><span class="label">Dirección</span>{{ $pedido->direccion ?: '—' }}</td>
        </tr>
    </table>

    <h2 class="section-title">Productos</h2>
    <table class="report-table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio unitario</th>
                <th>Subtotal</th>
                <th>Especificaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido->detalles as $detalle)
                @php
                    $medidas = collect([$detalle->alto, $detalle->ancho, $detalle->profundidad])
                        ->filter(fn ($medida) => filled($medida))
                        ->implode(' × ');
                @endphp
                <tr>
                    <td>{{ $detalle->producto }}</td>
                    <td>{{ $detalle->cantidad }}</td>
                    <td class="money">${{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                    <td class="money">${{ number_format((float) $detalle->cantidad * (float) $detalle->precio_unitario, 2) }}</td>
                    <td>
                        <strong>Medidas:</strong> {{ $medidas !== '' ? $medidas : '—' }}<br>
                        <strong>Material:</strong> {{ $detalle->material_principal ?: '—' }}<br>
                        <strong>Acabado/color:</strong> {{ $detalle->acabado_color ?: '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="payment-section">
        <h2 class="section-title">Resumen de pago</h2>
        <table class="payment-summary">
            <tr><td>Forma de pago</td><td>{{ $pedido->forma_pago }}</td></tr>
            @if ($pedido->forma_pago === 'Anticipo')
                <tr><td>Monto del anticipo</td><td>${{ number_format((float) $pedido->monto_anticipo, 2) }}</td></tr>
                <tr><td>Fecha del anticipo</td><td>{{ $pedido->fecha_anticipo?->format('d/m/Y') ?? '—' }}</td></tr>
                <tr><td>Saldo</td><td>${{ number_format(max(0, (float) $pedido->total - (float) $pedido->monto_anticipo), 2) }}</td></tr>
            @endif
            <tr class="total"><td>Total</td><td>${{ number_format((float) $pedido->total, 2) }}</td></tr>
        </table>
    </div>
@endsection
