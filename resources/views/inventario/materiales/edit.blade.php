@extends('layouts.admin')

@section('title', 'Editar material')
@section('page_title', 'Editar material')

@section('content')
    @include('inventario.materiales._form', [
        'action' => route('inventario.materiales.update', $material->id),
        'method' => 'PUT',
        'submitLabel' => 'Guardar cambios',
    ])
@endsection
