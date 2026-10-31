@extends('layouts.admin')

@section('title', 'Crear cliente')
@section('page_title', 'Crear cliente')

@section('content')
    @include('clientes._form', [
        'action' => route('clientes.store'),
        'method' => 'POST',
        'submitLabel' => 'Guardar cliente',
        'filters' => $filters,
    ])
@endsection
