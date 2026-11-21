@extends('reportes.layout')

@section('titulo', 'Comprobante de entrega #'.$entrega->id)

@section('contenido')
    <style>
        .section-title { border-bottom: 1px solid #bbb; font-size: 14px; margin: 18px 0 8px; padding-bottom: 4px; }
        .data-table { border-collapse: collapse; width: 100%; }
        .data-table td { padding: 3px 8px 3px 0; vertical-align: top; width: 33%; }
        .label { color: #555; display: block; font-size: 9px; }
        .report-table tr { page-break-inside: avoid; }
        .report-table thead { display: table-header-group; }
        .evidence-image { max-height: 430px; max-width: 100%; }
        .evidence-block { margin-bottom: 16px; page-break-inside: avoid; text-align: center; }
        .signature { max-height: 220px; max-width: 100%; }
        .unavailable { border: 1px solid #bbb; padding: 24px; }
    </style>

    <h2 class="section-title">Datos de la entrega</h2>
    <table class="data-table">
        <tr>
            <td><span class="label">Pedido</span>#{{ $entrega->pedido_id }}</td>
            <td><span class="label">Cliente</span>{{ $entrega->pedido?->cliente?->nombre ?? '—' }}</td>
            <td><span class="label">Dirección</span>{{ $entrega->pedido?->direccion ?: '—' }}</td>
        </tr>
        <tr>
            <td><span class="label">Chofer</span>{{ $entrega->chofer?->name ?? '—' }}</td>
            <td><span class="label">Fecha asignada</span>{{ $entrega->fecha_asignada?->format('d/m/Y') ?? '—' }}</td>
            <td><span class="label">Fecha y hora de entrega</span>{{ $entrega->estado_fecha_hora?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    </table>

    <h2 class="section-title">Productos entregados</h2>
    <table class="report-table">
        <thead><tr><th>Producto</th><th>Cantidad</th></tr></thead>
        <tbody>
            @foreach ($entrega->pedido->detalles as $detalle)
                <tr><td>{{ $detalle->producto }}</td><td>{{ $detalle->cantidad }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="section-title">Receptor</h2>
    <p>{{ $entrega->receptor_nombre }}</p>

    <div class="evidence-block">
        <h2 class="section-title">Firma</h2>
        @if ($entrega->sin_firma)
            <p><strong>Sin firma</strong></p>
            <p><strong>Motivo:</strong> {{ $entrega->motivo_sin_firma ?: '—' }}</p>
        @elseif ($firma)
            <img src="{{ $firma }}" class="signature" alt="Firma del receptor">
        @else
            <p class="unavailable">Firma no disponible</p>
        @endif
    </div>

    @foreach ($fotos as $foto)
        <div class="evidence-block">
            @if ($loop->first)
                <h2 class="section-title">Fotos de la entrega</h2>
            @endif
            @if ($foto)
                <img src="{{ $foto['dataUri'] }}" width="{{ $foto['width'] }}" height="{{ $foto['height'] }}" class="evidence-image" alt="Foto de evidencia">
            @else
                <p class="unavailable">Foto no disponible</p>
            @endif
        </div>
    @endforeach
@endsection
