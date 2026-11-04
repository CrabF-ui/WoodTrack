@extends('layouts.app')

@section('title', 'Editar entrega')

@section('content')
    <h1 class="h3 mb-4">Editar entrega #{{ $entrega->id }}</h1>
    <div class="card"><div class="card-body">
        @include('entregas._form', ['action' => route('entregas.update', $entrega), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
    </div></div>
@endsection
