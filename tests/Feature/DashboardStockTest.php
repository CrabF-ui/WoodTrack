<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_omite_la_tabla_de_stock_y_chofer_no_accede(): void
    {
        $this->crearMaterial('Pino visible', 'madera', 'pieza', '1.00', '2.00');

        foreach ([User::factory()->create(), User::factory()->encargadoInventario()->create()] as $usuario) {
            $this->actingAs($usuario)->get(route('dashboard'))
                ->assertOk()
                ->assertDontSee('Stock actual contra mínimo')
                ->assertDontSee('Pino visible');
        }

        $this->actingAs(User::factory()->chofer()->create())->get(route('dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->sinRol()->create())->get(route('dashboard'))->assertForbidden();
    }

    private function crearMaterial(string $nombre, string $tipo, string $unidad, string $actual, string $minimo): void
    {
        Material::create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'unidad_medida' => $unidad,
            'stock_actual' => $actual,
            'stock_minimo' => $minimo,
        ]);
    }
}
