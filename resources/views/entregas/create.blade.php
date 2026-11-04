@extends('layouts.app')

@section('title', 'Asignar entrega')

@section('content')
    <h1 class="h3 mb-4">Asignar entrega</h1>
    <div class="card"><div class="card-body">
        @include('entregas._form', ['action' => route('entregas.store'), 'method' => 'POST', 'submitLabel' => 'Asignar entrega'])
    </div></div>
@endsection
