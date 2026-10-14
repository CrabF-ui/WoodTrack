@extends('layouts.app')

@section('title', 'Crear pedido')

@section('content')
    <h1 class="h3 mb-3">Crear pedido</h1>
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @include('pedidos._form', [
        'action' => route('pedidos.store'),
        'method' => 'POST',
        'submitLabel' => 'Guardar pedido',
    ])
@endsection
