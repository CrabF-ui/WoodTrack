@extends('layouts.admin')

@section('title', 'Editar pedido')
@section('page_title', 'Editar pedido #'.$pedido->id)

@section('content')
    @include('pedidos._form', [
        'action' => route('pedidos.update', $pedido->id),
        'method' => 'PUT',
        'submitLabel' => 'Guardar cambios',
    ])
@endsection
