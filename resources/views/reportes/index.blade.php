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
                <div class="border-top pt-3 mb-3">
                    <h3 class="h5">Problemas con entregas</h3>
                    <form method="GET" action="{{ route('reportes.problemas-entregas') }}" class="row g-2 align-items-end" target="_blank">
                        <div class="col-md-4">
                            <label for="desde" class="form-label">Desde</label>
                            <input type="date" name="desde" id="desde" class="form-control @error('desde') is-invalid @enderror" value="{{ old('desde', $periodoPredeterminado['desde']) }}">
                            @error('desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="hasta" class="form-label">Hasta</label>
                            <input type="date" name="hasta" id="hasta" class="form-control @error('hasta') is-invalid @enderror" value="{{ old('hasta', $periodoPredeterminado['hasta']) }}">
                            @error('hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">Generar Problemas con entregas PDF</button>
                        </div>
                    </form>
                </div>
                <ul>
                    <li>Nota de pedido: se genera desde el listado de <a href="{{ route('pedidos.index') }}">Pedidos</a> (botón «Nota PDF»).</li>
                    <li>Comprobante de entrega: se genera desde el listado de <a href="{{ route('entregas.index') }}">Entregas</a> cuando la entrega tiene evidencia completa.</li>
                </ul>
                <a href="{{ route('reportes.prueba') }}" class="btn btn-outline-primary">Probar el diseño base del PDF</a>
            @endif
        </div>
    </div>
@endsection
