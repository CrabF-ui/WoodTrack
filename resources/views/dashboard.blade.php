@extends('layouts.admin')

@section('title', 'Inicio')
@section('page_title', 'Inicio')

@section('content')
    <p class="text-muted mb-4">Hola, {{ auth()->user()->name }}.</p>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Pedidos</h2>
                    <p class="card-text">Consulta y administra los pedidos de venta.</p>
                    <a href="{{ route('pedidos.index') }}" class="btn btn-primary">Ver pedidos</a>
                </div>
            </div>
        </div>
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
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Clientes</h2>
                    <p class="card-text">Próximamente</p>
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Próximamente</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Inventario</h2>
                    <p class="card-text">Próximamente</p>
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Próximamente</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Usuarios</h2>
                    <p class="card-text">Próximamente</p>
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Próximamente</span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="card-title">Reportes</h2>
                    <p class="card-text">Próximamente</p>
                    <span class="btn btn-outline-secondary disabled" aria-disabled="true">Próximamente</span>
                </div>
            </div>
        </div>
    </div>
@endsection
