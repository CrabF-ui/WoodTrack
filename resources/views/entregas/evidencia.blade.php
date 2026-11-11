@extends('layouts.admin')

@section('title', 'Evidencia de la entrega')
@section('page_title', 'Evidencia de la entrega')

@section('content')
    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title">Datos de la entrega</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><strong>Pedido:</strong> {{ $entrega->pedido ? '#'.$entrega->pedido->id : '—' }}</div>
                <div class="col-md-4"><strong>Cliente:</strong> {{ $entrega->pedido?->cliente?->nombre ?? '—' }}</div>
                <div class="col-md-4"><strong>Dirección:</strong> {{ $entrega->pedido?->direccion ?: '—' }}</div>
                <div class="col-md-4"><strong>Chofer:</strong> {{ $entrega->chofer?->name ?? '—' }}</div>
                <div class="col-md-4"><strong>Fecha asignada:</strong> {{ $entrega->fecha_asignada?->format('d/m/Y') ?? '—' }}</div>
                <div class="col-md-4">
                    <strong>Estado:</strong>
                    <span class="badge {{ $entrega->estado === 'pendiente' ? 'text-bg-warning' : ($entrega->estado === 'en_camino' ? 'text-bg-info' : ($entrega->estado === 'entregada' ? 'text-bg-success' : 'text-bg-danger')) }}">{{ Str::headline($entrega->estado) }}</span>
                </div>
                <div class="col-md-4"><strong>Último cambio de estado:</strong> {{ $entrega->estado_fecha_hora?->format('d/m/Y H:i') ?? '—' }}</div>
            </div>
        </div>
    </div>

    @if ($entrega->estado === \App\Models\Entrega::ESTADO_ENTREGADA && $entrega->receptor_nombre && $entrega->fotos->isNotEmpty() && ($entrega->firma_path || $entrega->sin_firma))
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Evidencia registrada</h2></div>
            <div class="card-body">
                <p><strong>Nombre del receptor:</strong> {{ $entrega->receptor_nombre }}</p>
                <h3 class="h5">Fotos de la entrega</h3>
                <div class="row g-3 mb-4">
                    @foreach ($entrega->fotos as $foto)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a href="{{ route('entregas.evidencia.foto', [$entrega, $foto]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('entregas.evidencia.foto', [$entrega, $foto]) }}" class="img-fluid rounded border w-100 object-fit-cover" style="max-height: 180px;" alt="Foto de evidencia de la entrega {{ $entrega->id }}">
                            </a>
                        </div>
                    @endforeach
                </div>

                @if ($entrega->sin_firma)
                    <p class="mb-1"><strong>Sin firma</strong></p>
                    <p class="mb-0"><strong>Motivo:</strong> {{ $entrega->motivo_sin_firma ?: '—' }}</p>
                @else
                    <h3 class="h5">Firma del receptor</h3>
                    <img src="{{ route('entregas.evidencia.firma', $entrega) }}" class="img-fluid rounded border" style="max-height: 320px;" alt="Firma del receptor de la entrega {{ $entrega->id }}">
                @endif
            </div>
        </div>
    @elseif ($entrega->estado === \App\Models\Entrega::ESTADO_INCIDENCIA && $entrega->incidencia_motivo)
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Incidencia registrada</h2></div>
            <div class="card-body">
                <p><strong>Motivo de la incidencia:</strong> {{ $entrega->incidencia_motivo }}</p>
                <p class="mb-0"><strong>Fecha y hora:</strong> {{ $entrega->estado_fecha_hora?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
        </div>
    @else
        <div class="alert alert-info">Esta entrega todavía no tiene evidencia registrada.</div>
    @endif

    <a href="{{ route('entregas.index') }}" class="btn btn-outline-secondary">Volver a entregas</a>
@endsection
