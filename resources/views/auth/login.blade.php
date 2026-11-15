@extends('layouts.login')

@section('content')
    <main class="login-page">
        <p class="login-brand">WoodTrack</p>
        <p class="login-subtitle">Sistema interno · Gestión de pedidos</p>

        <div class="login-card">
            <div class="login-card__body">
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="login-form">
                        <h1 class="login-title">Iniciar sesión</h1>

                        @if (session('status'))
                            <div class="status-message" role="status">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="error-message" role="alert">
                                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 7v6"></path>
                                    <path d="M12 17h.01"></path>
                                </svg>
                                <div>
                                    <strong>No se pudo iniciar sesión.</strong><br>
                                    {{ $errors->first() }}
                                </div>
                            </div>
                        @endif

                        <div class="form-field">
                            <label class="form-label" for="email">Correo electrónico</label>
                            <input
                                id="email"
                                class="form-input{{ $errors->any() ? ' form-input--invalid' : '' }}"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                @if ($errors->any()) aria-invalid="true" @endif
                            >
                        </div>

                        <div class="form-field">
                            <label class="form-label" for="password">Contraseña</label>
                            <input
                                id="password"
                                class="form-input{{ $errors->any() ? ' form-input--invalid' : '' }}"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                @if ($errors->any()) aria-invalid="true" @endif
                            >
                        </div>

                        <label class="remember-option" for="remember">
                            <input id="remember" type="checkbox" name="remember">
                            <span>Mantener la sesión iniciada</span>
                        </label>

                        <button class="login-button" type="submit">Iniciar sesión</button>

                        <p class="login-help">¿No tienes cuenta? Solicítala al administrador.</p>
                    </div>
                </form>
            </div>
        </div>
    </main>
@endsection
