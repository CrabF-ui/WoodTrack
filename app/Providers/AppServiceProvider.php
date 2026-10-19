<?php

namespace App\Providers;

use App\Repositories\Contracts\InventarioRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Repositories\Eloquent\InventarioRepository;
use App\Repositories\Eloquent\MaterialRepository;
use App\Repositories\Eloquent\PedidoRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registro de la aplicacion.
     */
    public function register(): void
    {
        $this->app->bind(PedidoRepositoryInterface::class, PedidoRepository::class); //
        $this->app->bind(MaterialRepositoryInterface::class, MaterialRepository::class);
        $this->app->bind(InventarioRepositoryInterface::class, InventarioRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    Paginator::useBootstrapFive();
}
}
