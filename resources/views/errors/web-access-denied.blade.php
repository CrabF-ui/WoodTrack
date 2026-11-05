<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso no disponible</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
    <main class="card shadow-sm text-center" style="max-width: 32rem">
        <div class="card-body p-5">
            <h1 class="h4 mb-4">Tu cuenta no tiene acceso a la versión web</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Cerrar sesión</button>
            </form>
        </div>
    </main>
</body>
</html>
