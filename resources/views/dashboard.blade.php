@extends('layouts.admin')

@section('title', 'Inicio')
@section('page_title', 'Inicio')

@section('content')
    <p class="text-muted mb-4">Hola, {{ auth()->user()->name }}.</p>

    <div class="row g-3">
        @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Papelera</h2>
                    <p class="card-text">Revisa y restaura pedidos eliminados.</p>
                    <a href="{{ route('pedidos.index', ['estado' => 'eliminados']) }}" class="btn btn-outline-primary">Ver papelera</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $totalSinEntrega > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Pedidos completados sin entrega</h6>
                    <p class="display-6 mb-2">{{ $totalSinEntrega }}</p>
                    @if ($totalSinEntrega > 0)
                        <a href="{{ route('pedidos.index', ['sin_entrega' => 1]) }}">Ver pedidos</a>
                    @else
                        <small class="text-success">Todo en orden</small>
                    @endif
                </div>
            </div>
        </div>
        @endif
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $totalBajoStock > 0 ? 'border-warning' : '' }}">
                <div class="card-body">
                    <h6 class="card-subtitle text-muted mb-2">Materiales en bajo stock</h6>
                    <p class="display-6 mb-2">{{ $totalBajoStock }}</p>

                    @if ($totalBajoStock > 0)
                        <a href="{{ route('inventario.index', ['bajo_stock' => 1]) }}">Ver materiales</a>
                    @else
                        <small class="text-success">Todo en orden</small>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
