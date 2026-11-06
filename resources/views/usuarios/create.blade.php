@extends('layouts.admin')
@section('title', 'Crear usuario')
@section('page_title', 'Crear usuario')
@section('content')
    @include('usuarios._form', ['action' => route('usuarios.store'), 'method' => 'POST', 'submitLabel' => 'Guardar usuario', 'filters' => $filters])
@endsection
