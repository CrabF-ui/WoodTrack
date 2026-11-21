@extends('reportes.layout')

@section('titulo', 'Lista de compras')

@section('contenido')
    <style>
        .summary { font-weight: bold; margin-bottom: 14px; }
        .section-title { border-bottom: 1px solid #bbb; font-size: 14px; margin: 18px 0 8px; padding-bottom: 4px; }
        .report-table tr { page-break-inside: avoid; }
        .report-table thead { display: table-header-group; }
        .number { text-align: right; white-space: nowrap; }
        .write-in { min-width: 90px; }
        .empty-message { border: 1px solid #bbb; padding: 14px; text-align: center; }
    </style>

    <p class="summary">Total de materiales por comprar: {{ $materiales->count() }}</p>

    @if ($materiales->isEmpty())
        <p class="empty-message">No hay materiales por debajo del stock mínimo.</p>
    @else
        @foreach (['madera' => 'Maderas', 'herraje' => 'Herrajes', 'acabado' => 'Acabados'] as $tipo => $titulo)
            @php($materialesDelTipo = $materiales->where('tipo', $tipo))
            @if ($materialesDelTipo->isNotEmpty())
                <h2 class="section-title">{{ $titulo }}</h2>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Unidad</th>
                            <th>Stock actual</th>
                            <th>Stock mínimo</th>
                            <th>Faltante</th>
                            <th>Cantidad a comprar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($materialesDelTipo as $material)
                            <tr>
                                <td>{{ $material->nombre }}</td>
                                <td>{{ $material->unidad_medida }}</td>
                                <td class="number">{{ number_format((float) $material->stock_actual, 2) }}</td>
                                <td class="number">{{ number_format((float) $material->stock_minimo, 2) }}</td>
                                <td class="number">{{ number_format(max(0, (float) $material->stock_minimo - (float) $material->stock_actual), 2) }}</td>
                                <td class="write-in">&nbsp;</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @endif
@endsection
