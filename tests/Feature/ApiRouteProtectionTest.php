<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiRouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_rutas_api_requieren_autenticacion_y_rol_chofer(): void
    {
        // api/login se agregará en la tarjeta del login.
        $excepciones = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), $excepciones, true)) {
                continue;
            }

            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "La ruta {$route->uri()} debe requerir auth:sanctum.");
            $this->assertContains('api.chofer', $route->gatherMiddleware(), "La ruta {$route->uri()} debe requerir api.chofer.");
        }
    }
}
