@extends('layouts.admin')
@section('title', 'Editar usuario')
@section('page_title', 'Editar usuario')
@section('content')
    @include('usuarios._form', ['action' => route('usuarios.update', ['usuario' => $usuario->id] + $filters), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios', 'filters' => $filters])
    <form action="{{ route('usuarios.password', ['usuario' => $usuario->id] + $filters) }}" method="POST" class="mt-4">@csrf @method('PATCH')<div class="card"><div class="card-header"><h2 class="card-title">Restablecer contraseña</h2></div><div class="card-body"><div class="mb-3"><label for="password" class="form-label">Nueva contraseña</label><input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password" required autocomplete="new-password">@error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror</div><div class="mb-3"><label for="password_confirmation" class="form-label">Confirmar contraseña</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div></div><div class="card-footer text-end"><button class="btn btn-outline-primary">Restablecer contraseña</button></div></div></form>
@endsection
