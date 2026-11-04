@extends('layouts.admin')

@section('title', 'Editar entrega')
@section('page_title', 'Editar entrega')

@section('content')
    <div class="card"><div class="card-body">
        @include('entregas._form', ['action' => route('entregas.update', $entrega), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
    </div></div>
@endsection
