<?php

namespace App\Providers;

use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Eloquent\MaterialRepository;
use App\Repositories\Eloquent\PedidoRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registro de la aplicacion.
     */
    public function register(): void
    {
        $this->app->bind(PedidoRepositoryInterface::class, PedidoRepository::class); //
        $this->app->bind(MaterialRepositoryInterface::class, MaterialRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
