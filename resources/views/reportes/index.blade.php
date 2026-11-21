@extends('layouts.admin')

@section('title', 'Reportes')
@section('page_title', 'Reportes')

@section('content')
    <div class="card">
        <div class="card-header"><h2 class="card-title">Reportes disponibles</h2></div>
        <div class="card-body">
            @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
                <ul>
                    <li>Nota de pedido: se genera desde el listado de <a href="{{ route('pedidos.index') }}">Pedidos</a> (botón «Nota PDF»).</li>
                </ul>
                <a href="{{ route('reportes.prueba') }}" class="btn btn-outline-primary">Probar el diseño base del PDF</a>
            @else
                <p class="text-muted">Aún no hay reportes disponibles.</p>
            @endif
        </div>
    </div>
@endsection
