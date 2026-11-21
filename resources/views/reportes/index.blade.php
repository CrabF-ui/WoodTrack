@extends('layouts.admin')

@section('title', 'Reportes')
@section('page_title', 'Reportes')

@section('content')
    <div class="card">
        <div class="card-header"><h2 class="card-title">Reportes disponibles</h2></div>
        <div class="card-body">
            <p class="text-muted">Aún no hay reportes disponibles.</p>
            @if (auth()->user()->rol === \App\Models\User::ROL_ADMIN)
                <a href="{{ route('reportes.prueba') }}" class="btn btn-outline-primary">Probar el diseño base del PDF</a>
            @endif
        </div>
    </div>
@endsection
