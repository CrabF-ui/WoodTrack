@extends('layouts.admin')

@section('title', 'Reportes')
@section('page_title', 'Reportes')

@section('content')
    <div class="card">
        <div class="card-header"><h2 class="card-title">Reportes disponibles</h2></div>
        <div class="card-body">
            <div class="mb-3">
                <h3 class="h5">Lista de compras</h3>
                <p>Materiales que requieren reabastecimiento según su stock mínimo.</p>
                <a href="{{ route('reportes.lista-compras') }}" class="btn btn-primary" target="_blank" rel="noopener">Generar Lista de compras PDF</a>
            </div>
            @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
                <ul>
                    <li>Nota de pedido: se genera desde el listado de <a href="{{ route('pedidos.index') }}">Pedidos</a> (botón «Nota PDF»).</li>
                    <li>Comprobante de entrega: se genera desde el listado de <a href="{{ route('entregas.index') }}">Entregas</a> cuando la entrega tiene evidencia completa.</li>
                </ul>
                <a href="{{ route('reportes.prueba') }}" class="btn btn-outline-primary">Probar el diseño base del PDF</a>
            @endif
        </div>
    </div>
@endsection
