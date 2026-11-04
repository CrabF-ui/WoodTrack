@extends('layouts.admin')

@section('title', 'Asignar entrega')
@section('page_title', 'Asignar entrega')

@section('content')
    <div class="card"><div class="card-body">
        @include('entregas._form', ['action' => route('entregas.store'), 'method' => 'POST', 'submitLabel' => 'Asignar entrega'])
    </div></div>
@endsection
