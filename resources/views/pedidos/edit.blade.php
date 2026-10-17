@extends('layouts.app')

@section('title', 'Editar pedido')

@section('content')
    <h1 class="h3 mb-3">Editar pedido #{{ $pedido->id }}</h1>
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif
    @include('pedidos._form', [
        'action' => route('pedidos.update', $pedido->id),
        'method' => 'PUT',
        'submitLabel' => 'Guardar cambios',
    ])
@endsection
