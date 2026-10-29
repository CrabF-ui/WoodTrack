<aside class="app-sidebar bg-dark" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="brand-link text-decoration-none">
            <span class="brand-text fw-light">WoodTrack</span>
        </a>
    </div>
    <div class="sidebar-wrapper">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">
                <li class="nav-header">Sistema</li>
                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-house-door"></i><p>Inicio</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('pedidos.index') }}" class="nav-link {{ request()->routeIs('pedidos.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-clipboard-check"></i><p>Pedidos</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-people"></i><p>Clientes</p></a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('inventario.index') }}" class="nav-link {{ request()->routeIs('inventario.*') ? 'active' : '' }}"><i class="nav-icon bi bi-box-seam"></i><p>Inventario</p></a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-person-gear"></i><p>Usuarios</p></a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="nav-icon bi bi-graph-up"></i><p>Reportes</p></a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
