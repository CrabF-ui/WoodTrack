@extends('layouts.admin')

@section('title', 'Editar cliente')
@section('page_title', 'Editar cliente')

@section('content')
    @include('clientes._form', [
        'action' => route('clientes.update', $cliente->id),
        'method' => 'PUT',
        'submitLabel' => 'Guardar cambios',
        'filters' => $filters,
    ])
@endsection
