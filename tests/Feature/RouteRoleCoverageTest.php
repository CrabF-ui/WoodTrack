<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteRoleCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authenticated_route_has_role_middleware_except_explicit_auth_routes(): void
    {
        $exactExceptions = ['logout', 'password.confirm', 'confirm-password'];
        $exceptionPrefixes = ['verification.', 'password.'];
        $uncoveredRoutes = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (! in_array('auth', $middleware, true)) {
                continue;
            }

            $name = $route->getName() ?? $route->uri();
            $isException = in_array($name, $exactExceptions, true);
            foreach ($exceptionPrefixes as $prefix) {
                $isException = $isException || str_starts_with($name, $prefix);
            }

            if ($isException) {
                continue;
            }

            if (! collect($middleware)->contains(fn (string $entry): bool => str_starts_with($entry, 'rol:'))) {
                $uncoveredRoutes[] = $name;
            }
        }

        $this->assertSame([], $uncoveredRoutes, 'Rutas con auth sin middleware rol: '.implode(', ', $uncoveredRoutes));
    }
}
