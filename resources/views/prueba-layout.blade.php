@extends('layouts.admin')

@section('title', 'Prueba del layout | WoodTrack')
@section('page_title', 'Prueba del layout')

@section('content')
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Componentes de ejemplo</h2>
        </div>
        <div class="card-body">
            <p>Vista temporal para revisar el tema, la tabla y los botones antes de migrar las vistas internas.</p>
            <div class="table-responsive mb-4">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr><th>Pedido</th><th>Cliente</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>WT-001</td><td>Cliente de muestra</td><td><span class="badge text-bg-success">En proceso</span></td></tr>
                        <tr><td>WT-002</td><td>Otro cliente</td><td><span class="badge text-bg-secondary">Pendiente</span></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary">Botón primario</button>
                <button type="button" class="btn btn-accent">Botón de acento</button>
            </div>
        </div>
    </div>
@endsection
