@extends('reportes.layout')

@section('titulo', 'Reporte de varias páginas')

@section('contenido')
    <h1>Contenido de prueba</h1>
    <table class="report-table">
        <thead><tr><th>Fila</th><th>Descripción</th></tr></thead>
        <tbody>
            @foreach (range(1, 100) as $fila)
                <tr><td>{{ $fila }}</td><td>Año, diseño, cañón, niño, camión</td></tr>
            @endforeach
        </tbody>
    </table>
@endsection
