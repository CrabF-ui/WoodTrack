<!DOCTYPE html>
<html lang="es" data-bs-theme="light" data-lte-color-mode="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'WoodTrack'))</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @stack('styles')
</head>
<body class="layout-fixed sidebar-expand-lg">
    <div class="app-wrapper">
        @include('layouts.partials.navbar')
        @include('layouts.partials.sidebar')

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <h1 class="mb-0">@yield('page_title')</h1>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    @include('layouts.partials.flash')
                    @yield('content')
                </div>
            </div>
        </main>

        @include('layouts.partials.footer')
    </div>

    @stack('scripts')
</body>
</html>
