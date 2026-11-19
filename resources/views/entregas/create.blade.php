@extends('layouts.admin')

@section('title', $intentoAnterior ? 'Reasignar entrega' : 'Asignar entrega')
@section('page_title', $intentoAnterior ? 'Reasignar entrega' : 'Asignar entrega')

@section('content')
    <div class="card"><div class="card-body">
        @if ($intentoAnterior)
            <div class="alert alert-warning" role="alert">
                <strong>Incidencia previa.</strong>
                Intento asignado el {{ $intentoAnterior->fecha_asignada->format('d/m/Y') }}.
                Motivo: {{ $intentoAnterior->incidencia_motivo ?: 'Sin motivo registrado' }}.
            </div>
        @endif
        @include('entregas._form', ['action' => route('entregas.store'), 'method' => 'POST', 'submitLabel' => $intentoAnterior ? 'Reasignar entrega' : 'Asignar entrega'])
    </div></div>
@endsection
