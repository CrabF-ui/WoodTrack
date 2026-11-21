@extends('layouts.admin')

@section('title', 'Inicio')
@section('page_title', 'Inicio')

@section('content')
    <p class="text-muted mb-4">Hola, {{ auth()->user()->name }}.</p>

    <div class="row g-3">
        @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Papelera</h2>
                    <p class="card-text">Revisa y restaura pedidos eliminados.</p>
                    <a href="{{ route('pedidos.index', ['estado' => 'eliminados']) }}" class="btn btn-outline-primary">Ver papelera</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $totalSinEntrega > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Pedidos completados sin entrega</h6>
                    <p class="display-6 mb-2">{{ $totalSinEntrega }}</p>
                    @if ($totalSinEntrega > 0)
                        <a href="{{ route('pedidos.index', ['sin_entrega' => 1]) }}">Ver pedidos</a>
                    @else
                        <small class="text-success">Todo en orden</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $totalSinHerrajes > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Pedidos completados sin herrajes</h6>
                    <p class="display-6 mb-2">{{ $totalSinHerrajes }}</p>
                    @if ($totalSinHerrajes > 0)
                        <a href="{{ route('pedidos.index', ['sin_herrajes' => 1]) }}">Ver pedidos</a>
                    @else
                        <small class="text-success">Todo en orden</small>
                    @endif
                </div>
            </div>
        </div>
        @endif
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $totalBajoStock > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Materiales en bajo stock</h6>
                    <p class="display-6 mb-2">{{ $totalBajoStock }}</p>

                    @if ($totalBajoStock > 0)
                        <a href="{{ route('inventario.index', ['bajo_stock' => 1]) }}">Ver materiales</a>
                    @else
                        <small class="text-success">Todo en orden</small>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-3">
        @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
            <div class="col-md-6 col-xl-4 mt-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title h5">Entregas de hoy</h2>
                        @if (array_sum($entregasHoy) === 0)
                            <p class="text-muted mb-0">No hay entregas programadas para hoy.</p>
                        @else
                            <div class="position-relative" style="height: 260px;">
                                <canvas id="entregas-hoy-chart" role="img" aria-label="Entregas de hoy: {{ $entregasHoy['entregadas'] }} entregadas y {{ $entregasHoy['pendientes'] }} pendientes"></canvas>
                            </div>
                            <ul class="list-unstyled mt-3 mb-0">
                                <li><span class="badge" style="background-color:#198754">&nbsp;</span> Entregadas: {{ $entregasHoy['entregadas'] }}</li>
                                <li><span class="badge" style="background-color:#d97706">&nbsp;</span> Pendientes: {{ $entregasHoy['pendientes'] }}</li>
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4 mt-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title h5">Entregas de la semana</h2>
                        <p class="display-6 mb-2">{{ $entregasSemana['entregadas'] }} entregadas de {{ $entregasSemana['asignadas'] }} asignadas</p>
                        <small class="text-muted">Últimos 7 días</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-4 mt-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="card-title h5">Pedidos registrados</h2>
                        <p class="display-6 mb-2">${{ number_format($pedidosSemana, 2, '.', ',') }}</p>
                        <small class="text-muted">Últimos 7 días</small>
                    </div>
                </div>
            </div>
        @endif
</div>
@endsection

@if (auth()->user()->rol === \App\Models\User::ROL_ADMIN && array_sum($entregasHoy) > 0)
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            new Chart(document.getElementById('entregas-hoy-chart'), {
                type: 'doughnut',
                data: {
                    labels: ['Entregadas', 'Pendientes'],
                    datasets: [{ data: @json([$entregasHoy['entregadas'], $entregasHoy['pendientes']]), backgroundColor: ['#198754', '#d97706'] }],
                },
                options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
            });
        </script>
    @endpush
@endif
