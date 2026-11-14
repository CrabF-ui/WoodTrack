@extends('layouts.admin')

@section('title', 'Crear material')
@section('page_title', 'Crear material')

@section('content')
    @include('inventario.materiales._form', [
        'action' => route('inventario.materiales.store'),
        'method' => 'POST',
        'submitLabel' => 'Guardar material',
    ])
@endsection
