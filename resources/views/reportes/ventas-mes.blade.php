@extends('reportes.layout')

@section('titulo', 'Ventas del mes')

@section('contenido')
    <style>
        .month { font-size: 14px; font-weight: bold; margin-bottom: 12px; }
        .summary-table { border-collapse: collapse; margin-bottom: 14px; width: 100%; }
        .summary-table td { border: 1px solid #bbb; padding: 8px; text-align: center; width: 33%; }
        .summary-label { color: #555; display: block; font-size: 9px; }
        .section-title { border-bottom: 1px solid #bbb; font-size: 14px; margin: 18px 0 8px; padding-bottom: 4px; }
        .report-table tr { page-break-inside: avoid; }
        .report-table thead { display: table-header-group; }
        .money { text-align: right; white-space: nowrap; }
        .empty-message { border: 1px solid #bbb; padding: 14px; text-align: center; }
        .definition { border-top: 1px solid #bbb; color: #555; font-size: 9px; margin-top: 20px; padding-top: 8px; }
        .chart { margin: 14px 0; page-break-inside: avoid; text-align: center; }
        .chart img { height: auto; max-width: 100%; }
        .chart-empty { border: 1px solid #bbb; color: #555; padding: 14px; }
    </style>

    @php
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    @endphp

    <p class="month">Mes: {{ $meses[$mes->month] }} {{ $mes->year }}</p>

    <table class="summary-table">
        <tr>
            <td><span class="summary-label">Total vendido</span>${{ number_format($ventas['total'], 2) }}</td>
            <td><span class="summary-label">Número de pedidos</span>{{ $ventas['pedidos'] }}</td>
            <td><span class="summary-label">Ticket promedio</span>${{ number_format($ventas['ticketPromedio'], 2) }}</td>
        </tr>
    </table>

    <h2 class="section-title">Comparación con el mes anterior</h2>
    <table class="summary-table">
        <tr>
            <td><span class="summary-label">Total anterior</span>${{ number_format($ventas['comparacion']['totalAnterior'], 2) }}</td>
            <td><span class="summary-label">Pedidos anteriores</span>{{ $ventas['comparacion']['pedidosAnterior'] }}</td>
            <td><span class="summary-label">Diferencia</span>{{ $ventas['comparacion']['diferencia'] < 0 ? '-$'.number_format(abs($ventas['comparacion']['diferencia']), 2) : '$'.number_format($ventas['comparacion']['diferencia'], 2) }}</td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="summary-label">Variación</span>
                @if ($ventas['comparacion']['variacion'] === null)
                    N/D (sin ventas el mes anterior)
                @else
                    {{ number_format($ventas['comparacion']['variacion'], 2) }}%
                @endif
            </td>
        </tr>
    </table>

    <div class="chart">
        @if ($graficaProductos)
            <img src="{{ $graficaProductos }}" alt="Top 5 productos por cantidad vendida">
        @else
            <p class="chart-empty">Sin datos para graficar en el periodo</p>
        @endif
    </div>

    <div class="chart">
        @if ($graficaComparacion)
            <img src="{{ $graficaComparacion }}" alt="Ventas: mes anterior contra mes actual">
        @else
            <p class="chart-empty">Sin datos para graficar en el periodo</p>
        @endif
    </div>

    @if ($ventas['pedidos'] === 0)
        <p class="empty-message">No hay ventas registradas en el mes seleccionado.</p>
    @else
        <h2 class="section-title">Top 5 clientes</h2>
        <table class="report-table">
            <thead><tr><th>Cliente</th><th>Pedidos</th><th>Monto vendido</th></tr></thead>
            <tbody>
                @foreach ($ventas['topClientes'] as $cliente)
                    <tr>
                        <td>{{ $cliente['nombre'] }}</td>
                        <td>{{ $cliente['pedidos'] }}</td>
                        <td class="money">${{ number_format($cliente['monto'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2 class="section-title">Top 5 productos</h2>
        <table class="report-table">
            <thead><tr><th>Producto</th><th>Cantidad</th><th>Monto vendido</th></tr></thead>
            <tbody>
                @foreach ($ventas['topProductos'] as $producto)
                    <tr>
                        <td>{{ $producto['nombre'] }}</td>
                        <td>{{ fmod((float) $producto['cantidad'], 1.0) === 0.0 ? number_format($producto['cantidad']) : rtrim(number_format($producto['cantidad'], 2), '0') }}</td>
                        <td class="money">${{ number_format($producto['monto'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="definition">
        Definición de venta: suma de los totales de pedidos no cancelados y no eliminados según la fecha del pedido. Este reporte mide lo vendido y no refleja cobros, anticipos ni saldos.
    </p>
@endsection
