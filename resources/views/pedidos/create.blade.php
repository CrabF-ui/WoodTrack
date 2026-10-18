@extends('layouts.admin')

@section('title', 'Crear pedido')
@section('page_title', 'Crear pedido')

@section('content')
    @include('pedidos._form', [
        'action' => route('pedidos.store'),
        'method' => 'POST',
        'submitLabel' => 'Guardar pedido',
    ])
@endsection
