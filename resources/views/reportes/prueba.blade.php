@extends('reportes.layout')

@section('titulo', 'Diseño base de reportes')

@section('contenido')
    <h1>Reporte de prueba</h1>
    <p>Este documento valida la plantilla común para los reportes de WoodTrack.</p>
    <p>Año, diseño, cañón, niño, camión</p>

    <table class="report-table">
        <thead>
            <tr><th>Concepto</th><th>Valor</th></tr>
        </thead>
        <tbody>
            <tr><td>Formato</td><td>Carta vertical</td></tr>
            <tr><td>Tipografía</td><td>DejaVu Sans</td></tr>
        </tbody>
    </table>
@endsection
