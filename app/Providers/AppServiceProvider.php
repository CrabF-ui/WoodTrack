<?php

namespace App\Providers;

use App\Repositories\Contracts\InventarioRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\InventarioRepository;
use App\Repositories\Eloquent\MaterialRepository;
use App\Repositories\Eloquent\PedidoRepository;
use App\Repositories\Eloquent\ClienteRepository;
use App\Repositories\Eloquent\EntregaRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registro de la aplicacion.
     */
    public function register(): void
    {
        $this->app->bind(PedidoRepositoryInterface::class, PedidoRepository::class); //
        $this->app->bind(ClienteRepositoryInterface::class, ClienteRepository::class);
        $this->app->bind(MaterialRepositoryInterface::class, MaterialRepository::class);
        $this->app->bind(InventarioRepositoryInterface::class, InventarioRepository::class);
        $this->app->bind(EntregaRepositoryInterface::class, EntregaRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    Paginator::useBootstrapFive();
    RateLimiter::for('api-login', function (\Illuminate\Http\Request $request): Limit {
        $valorEmail = $request->input('email');
        $email = mb_strtolower(is_string($valorEmail) ? $valorEmail : '');

        return Limit::perMinute(5)
            ->by($email.'|'.$request->ip())
            ->response(fn () => response()->json([
                'message' => 'Demasiados intentos. Intenta de nuevo en un minuto.',
            ], 429));
    });
}
}
