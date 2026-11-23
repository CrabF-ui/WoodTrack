@extends('layouts.admin')

@section('title', 'Inicio')
@section('page_title', 'Inicio')

@section('content')
    <p class="text-muted mb-4">Hola, {{ auth()->user()->name }}.</p>

    @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
        @php
            $mesActual = now()->locale('es')->translatedFormat('F Y');
            $mesAnterior = now()->copy()->subMonthNoOverflow()->locale('es')->translatedFormat('F');
            $variacionTicket = $indicadoresMes['ticketAnterior'] > 0 ? (($indicadoresMes['ticketPromedio'] - $indicadoresMes['ticketAnterior']) / $indicadoresMes['ticketAnterior']) * 100 : null;
        @endphp
        <section aria-labelledby="indicadores-mes-titulo" class="mb-4">
            <h2 id="indicadores-mes-titulo" class="h4 mb-1">Indicadores del mes</h2>
            <p class="text-muted text-capitalize mb-3">{{ $mesActual }}</p>
            <div class="row g-3">
                <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                    <h3 class="card-title h6 text-muted">Ventas del mes</h3>
                    <p class="display-6 mb-1">${{ number_format($indicadoresMes['total'], 2, '.', ',') }}</p>
                    <p class="mb-2">{{ $indicadoresMes['pedidos'] }} {{ $indicadoresMes['pedidos'] === 1 ? 'pedido' : 'pedidos' }}</p>
                    @if ($indicadoresMes['variacion'] === null)
                        <small class="text-muted">N/D vs mes anterior</small>
                    @else
                        <small class="{{ $indicadoresMes['variacion'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $indicadoresMes['variacion'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($indicadoresMes['variacion']), 1) }} % vs mes anterior</small>
                    @endif
                </div></div></div>
                <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                    <h3 class="card-title h6 text-muted">Producto más vendido</h3>
                    @if ($indicadoresMes['productoPrincipal'])
                        <p class="h4 mb-2">{{ $indicadoresMes['productoPrincipal']['nombre'] }}</p>
                        <p class="mb-0">{{ $indicadoresMes['productoPrincipal']['cantidad'] + 0 }} unidades</p>
                    @else
                        <p class="text-muted mb-0">Sin ventas este mes</p>
                    @endif
                </div></div></div>
                <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                    <h3 class="card-title h6 text-muted">Ticket promedio</h3>
                    <p class="display-6 mb-2">${{ number_format($indicadoresMes['ticketPromedio'], 2, '.', ',') }}</p>
                    @if ($variacionTicket === null)
                        <small class="text-muted">N/D vs mes anterior</small>
                    @else
                        <small class="{{ $variacionTicket >= 0 ? 'text-success' : 'text-danger' }}">{{ $variacionTicket >= 0 ? '▲' : '▼' }} {{ number_format(abs($variacionTicket), 1) }} % vs mes anterior</small>
                    @endif
                </div></div></div>
                <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                    <h3 class="card-title h6 text-muted">Cliente principal</h3>
                    @if ($indicadoresMes['clientePrincipal'])
                        <p class="h4 mb-2">{{ $indicadoresMes['clientePrincipal']['nombre'] }}</p>
                        <p class="mb-1">${{ number_format($indicadoresMes['clientePrincipal']['monto'], 2, '.', ',') }}</p>
                        <small class="text-muted">{{ $indicadoresMes['clientePrincipal']['pedidos'] }} {{ $indicadoresMes['clientePrincipal']['pedidos'] === 1 ? 'pedido' : 'pedidos' }}</small>
                    @else
                        <p class="text-muted mb-0">Sin ventas este mes</p>
                    @endif
                </div></div></div>
            </div>
        </section>
    @endif

    <div class="row g-3">
        @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
            <div class="col-md-6 col-xl-3"><div class="card h-100"><div class="card-body">
                <h2 class="card-title">Papelera</h2><p class="card-text">Revisa y restaura pedidos eliminados.</p>
                <a href="{{ route('pedidos.index', ['estado' => 'eliminados']) }}" class="btn btn-outline-primary">Ver papelera</a>
            </div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card h-100 {{ $totalSinEntrega > 0 ? 'border-warning' : '' }}"><div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Pedidos completados sin entrega</h6><p class="display-6 mb-2">{{ $totalSinEntrega }}</p>
                @if ($totalSinEntrega > 0)<a href="{{ route('pedidos.index', ['sin_entrega' => 1]) }}">Ver pedidos</a>@else<small class="text-success">Todo en orden</small>@endif
            </div></div></div>
            <div class="col-md-6 col-xl-3"><div class="card h-100 {{ $totalSinHerrajes > 0 ? 'border-warning' : '' }}"><div class="card-body">
                <h6 class="card-subtitle text-muted mb-2">Pedidos completados sin herrajes</h6><p class="display-6 mb-2">{{ $totalSinHerrajes }}</p>
                @if ($totalSinHerrajes > 0)<a href="{{ route('pedidos.index', ['sin_herrajes' => 1]) }}">Ver pedidos</a>@else<small class="text-success">Todo en orden</small>@endif
            </div></div></div>
        @endif
        <div class="col-md-6 {{ auth()->user()->rol === \App\Models\User::ROL_ADMIN ? 'col-xl-3' : 'col-xl-4' }}"><div class="card h-100 {{ $totalBajoStock > 0 ? 'border-warning' : '' }}"><div class="card-body">
            <h6 class="card-subtitle text-muted mb-2">Materiales en bajo stock</h6><p class="display-6 mb-2">{{ $totalBajoStock }}</p>
            @if ($totalBajoStock > 0)<a href="{{ route('inventario.index', ['bajo_stock' => 1]) }}">Ver materiales</a>@else<small class="text-success">Todo en orden</small>@endif
        </div></div></div>
    </div>

    @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
        <div class="row g-3 mt-3">
            <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body">
                <h2 class="card-title h5">Entregas de hoy</h2>
                @if (array_sum($entregasHoy) === 0)<p class="text-muted mb-0">No hay entregas programadas para hoy.</p>@else
                    <ul class="list-unstyled mb-0"><li><span class="badge" style="background-color:#198754">&nbsp;</span> Entregadas: {{ $entregasHoy['entregadas'] }}</li><li><span class="badge" style="background-color:#d97706">&nbsp;</span> Pendientes: {{ $entregasHoy['pendientes'] }}</li></ul>
                @endif
            </div></div></div>
            <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><h2 class="card-title h5">Entregas de la semana</h2><p class="display-6 mb-2">{{ $entregasSemana['entregadas'] }} entregadas de {{ $entregasSemana['asignadas'] }} asignadas</p><small class="text-muted">Últimos 7 días</small></div></div></div>
            <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><h2 class="card-title h5">Pedidos registrados</h2><p class="display-6 mb-2">${{ number_format($pedidosSemana, 2, '.', ',') }}</p><small class="text-muted">Últimos 7 días</small></div></div></div>
        </div>

        <section aria-labelledby="graficas-titulo" class="mt-5">
            <h2 id="graficas-titulo" class="h4 mb-3">Gráficas</h2>
            <div class="row g-3">
                <div class="col-xl-6"><div class="card h-100"><div class="card-body" style="min-height: 350px"><h3 class="h5">Ventas</h3>
                    @if ($indicadoresMes['totalAnterior'] == 0 && $indicadoresMes['total'] == 0)<p class="text-muted mb-0">Sin datos del mes</p>@else<div style="height: 300px"><canvas id="ventas-mes-chart" role="img" aria-label="Ventas: {{ $mesAnterior }} ${{ number_format($indicadoresMes['totalAnterior'], 2) }} y {{ now()->locale('es')->translatedFormat('F') }} ${{ number_format($indicadoresMes['total'], 2) }}"></canvas></div>@endif
                </div></div></div>
                <div class="col-xl-6"><div class="card h-100"><div class="card-body" style="min-height: 350px"><h3 class="h5">Top 5 productos por cantidad</h3>
                    @if ($indicadoresMes['topProductos']->isEmpty())<p class="text-muted mb-0">Sin datos del mes</p>@else<div style="height: 300px"><canvas id="productos-mes-chart" role="img" aria-label="Top productos: {{ $indicadoresMes['topProductos']->map(fn ($p) => $p['nombre'].' '.$p['cantidad'].' unidades')->implode(', ') }}"></canvas></div>@endif
                </div></div></div>
                <div class="col-xl-6"><div class="card h-100"><div class="card-body" style="min-height: 350px"><h3 class="h5">Top 5 clientes por monto</h3>
                    @if ($indicadoresMes['topClientes']->isEmpty())<p class="text-muted mb-0">Sin datos del mes</p>@else<div style="height: 300px"><canvas id="clientes-mes-chart" role="img" aria-label="Top clientes: {{ $indicadoresMes['topClientes']->map(fn ($c) => $c['nombre'].' $'.number_format($c['monto'], 2))->implode(', ') }}"></canvas></div>@endif
                </div></div></div>
                <div class="col-xl-6"><div class="card h-100"><div class="card-body" style="min-height: 350px"><h3 class="h5">Entregas de hoy</h3>
                    @if (array_sum($entregasHoy) === 0)<p class="text-muted mb-0">Sin entregas hoy</p>@else<div style="height: 300px"><canvas id="entregas-hoy-chart" role="img" aria-label="Entregas de hoy: {{ $entregasHoy['entregadas'] }} entregadas y {{ $entregasHoy['pendientes'] }} pendientes"></canvas></div>@endif
                </div></div></div>
            </div>
        </section>
    @endif
@endsection

@if (auth()->user()->rol === \App\Models\User::ROL_ADMIN && ($indicadoresMes['totalAnterior'] != 0 || $indicadoresMes['total'] != 0 || $indicadoresMes['topProductos']->isNotEmpty() || $indicadoresMes['topClientes']->isNotEmpty() || array_sum($entregasHoy) > 0))
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            const palette = { primary: '#0d6efd', success: '#198754', warning: '#d97706', info: '#0dcaf0' };
            const money = value => '$' + Number(value).toLocaleString('es-MX', { maximumFractionDigits: 2 });
            const truncate = value => value.length > 24 ? value.slice(0, 21) + '…' : value;
            const baseOptions = { maintainAspectRatio: false, responsive: true, plugins: { legend: { display: false } } };
            const horizontalOptions = moneyValues => ({ ...baseOptions, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { callback: value => moneyValues ? money(value) : Number(value).toLocaleString('es-MX') } }, y: { ticks: { callback: function(value) { return truncate(this.getLabelForValue(value)); } } } } });
            const ventasCanvas = document.getElementById('ventas-mes-chart');
            if (ventasCanvas) new Chart(ventasCanvas, { type: 'bar', data: { labels: @json([$mesAnterior, now()->locale('es')->translatedFormat('F')]), datasets: [{ data: @json([$indicadoresMes['totalAnterior'], $indicadoresMes['total']]), backgroundColor: [palette.warning, palette.primary] }] }, options: { ...baseOptions, scales: { y: { beginAtZero: true, ticks: { callback: money } } }, plugins: { ...baseOptions.plugins, tooltip: { callbacks: { label: context => money(context.raw) } } } } });
            const productosCanvas = document.getElementById('productos-mes-chart');
            if (productosCanvas) new Chart(productosCanvas, { type: 'bar', data: { labels: @json($indicadoresMes['topProductos']->pluck('nombre')), datasets: [{ data: @json($indicadoresMes['topProductos']->pluck('cantidad')), backgroundColor: palette.success }] }, options: { ...horizontalOptions(false), plugins: { ...baseOptions.plugins, tooltip: { callbacks: { title: items => items[0].chart.data.labels[items[0].dataIndex], label: context => Number(context.raw).toLocaleString('es-MX') + ' unidades' } } } } });
            const clientesCanvas = document.getElementById('clientes-mes-chart');
            if (clientesCanvas) new Chart(clientesCanvas, { type: 'bar', data: { labels: @json($indicadoresMes['topClientes']->pluck('nombre')), datasets: [{ data: @json($indicadoresMes['topClientes']->pluck('monto')), backgroundColor: palette.info }] }, options: { ...horizontalOptions(true), plugins: { ...baseOptions.plugins, tooltip: { callbacks: { title: items => items[0].chart.data.labels[items[0].dataIndex], label: context => money(context.raw) } } } } });
            const entregasCanvas = document.getElementById('entregas-hoy-chart');
            if (entregasCanvas) new Chart(entregasCanvas, { type: 'doughnut', data: { labels: ['Entregadas', 'Pendientes'], datasets: [{ data: @json([$entregasHoy['entregadas'], $entregasHoy['pendientes']]), backgroundColor: [palette.success, palette.warning] }] }, options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
        </script>
    @endpush
@endif
