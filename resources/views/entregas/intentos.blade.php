@extends('layouts.admin')

@section('title', 'Intentos de entrega')
@section('page_title', 'Intentos de entrega')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h2 class="h5 mb-3">Pedido #{{ $pedido->id }}</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Cliente</dt><dd class="col-sm-9">{{ $pedido->cliente?->nombre ?? '—' }}</dd>
                        <dt class="col-sm-3">Dirección</dt><dd class="col-sm-9">{{ $pedido->direccion ?: '—' }}</dd>
                        <dt class="col-sm-3">Estado</dt><dd class="col-sm-9"><span class="badge {{ $pedido->estado_badge_class }}">{{ $pedido->estado_label }}</span></dd>
                    </dl>
                </div>
                @if ($intentos->first()->estado === \App\Models\Entrega::ESTADO_INCIDENCIA && $pedido->estaCompletadoSinEntrega())
                    <a href="{{ route('entregas.create', ['pedido_id' => $pedido->id]) }}" class="btn btn-danger">Reasignar entrega</a>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Historial de intentos</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>#Intento</th><th>Fecha asignada</th><th>Chofer</th><th>Estado</th><th>Motivo de incidencia</th><th></th></tr></thead>
                <tbody>
                    @foreach ($intentos as $intento)
                        <tr>
                            <td>{{ $intentos->count() - $loop->index }}</td>
                            <td>{{ $intento->fecha_asignada->format('d/m/Y') }}</td>
                            <td>{{ $intento->chofer->name }}</td>
                            <td><span class="badge {{ $intento->estado === 'pendiente' ? 'text-bg-warning' : ($intento->estado === 'en_camino' ? 'text-bg-info' : ($intento->estado === 'entregada' ? 'text-bg-success' : 'text-bg-danger')) }}">{{ Str::headline($intento->estado) }}</span></td>
                            <td>{{ $intento->incidencia_motivo ?: '—' }}</td>
                            <td>
                                @if (in_array($intento->estado, [\App\Models\Entrega::ESTADO_ENTREGADA, \App\Models\Entrega::ESTADO_INCIDENCIA], true))
                                    <a href="{{ route('entregas.evidencia', $intento) }}" class="btn btn-sm btn-outline-secondary">Ver evidencia</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <a href="{{ route('entregas.index', $filtros) }}" class="btn btn-outline-secondary mt-3">Volver a Entregas</a>
@endsection
