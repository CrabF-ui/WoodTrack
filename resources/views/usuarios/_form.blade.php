@if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
<form action="{{ $action }}" method="POST">@csrf @if ($method !== 'POST') @method($method) @endif
    <div class="card"><div class="card-header"><h2 class="card-title">Datos del usuario</h2></div><div class="card-body">
        <div class="mb-3"><label for="name" class="form-label">Nombre</label><input class="form-control @error('name') is-invalid @enderror" type="text" id="name" name="name" value="{{ old('name', $usuario->name ?? '') }}" maxlength="255" required>@error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
        <div class="mb-3"><label for="email" class="form-label">Correo</label><input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email', $usuario->email ?? '') }}" maxlength="255" required>@error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
        <div class="mb-3"><label for="rol" class="form-label">Rol</label><select class="form-select @error('rol') is-invalid @enderror" id="rol" name="rol" required><option value="">Selecciona un rol</option><option value="admin" @selected(old('rol', $usuario->rol ?? '') === 'admin')>Administrador</option><option value="encargado_inventario" @selected(old('rol', $usuario->rol ?? '') === 'encargado_inventario')>Encargado de inventario</option><option value="chofer" @selected(old('rol', $usuario->rol ?? '') === 'chofer')>Chofer</option></select>@error('rol') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
        @if (! isset($usuario))
            <div class="mb-3"><label for="password" class="form-label">Contraseña temporal</label><input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password" required autocomplete="new-password">@error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
            <div class="mb-3"><label for="password_confirmation" class="form-label">Confirmar contraseña</label><input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
        @endif
    </div></div>
    <div class="d-flex gap-2 justify-content-end mt-3"><a href="{{ route('usuarios.index', $filters ?? []) }}" class="btn btn-outline-primary">Cancelar</a><button type="submit" class="btn btn-primary">{{ $submitLabel }}</button></div>
</form>
