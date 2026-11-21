@extends('reportes.layout')

@section('titulo', 'Problemas con entregas')

@section('contenido')
    <style>
        .period { font-weight: bold; margin-bottom: 8px; }
        .summary { margin-bottom: 14px; }
        .report-table tr { page-break-inside: avoid; }
        .report-table thead { display: table-header-group; }
        .empty-message { border: 1px solid #bbb; padding: 14px; text-align: center; }
        .nowrap { white-space: nowrap; }
    </style>

    <p class="period">Periodo: {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>
    <p class="summary">
        Total de incidencias: {{ $resumen['total'] }} |
        Sin atender: {{ $resumen['sinAtender'] }} |
        Reasignadas: {{ $resumen['reasignadas'] }} |
        Resueltas: {{ $resumen['resueltas'] }}
    </p>

    @if ($incidencias->isEmpty())
        <p class="empty-message">No hay incidencias de entrega en el periodo seleccionado.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Chofer</th>
                    <th>Fecha de incidencia</th>
                    <th>Motivo</th>
                    <th>Acción siguiente</th>
                    <th>Situación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidencias as $incidencia)
                    <tr>
                        <td class="nowrap">#{{ $incidencia->pedido_id }}</td>
                        <td>{{ $incidencia->pedido?->cliente?->nombre ?? '—' }}</td>
                        <td>{{ $incidencia->chofer?->name ?? '—' }}</td>
                        <td class="nowrap">{{ $incidencia->estado_fecha_hora?->copy()->setTimezone(config('reportes.zona_horaria'))->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ $incidencia->incidencia_motivo ?: '—' }}</td>
                        <td>{{ $incidencia->accion_siguiente ?: '—' }}</td>
                        <td>{{ $incidencia->situacion }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
