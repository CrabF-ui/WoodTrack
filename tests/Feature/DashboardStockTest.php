<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_muestra_materiales_individuales_ordenados_y_marcados(): void
    {
        $this->crearMaterial('Cedro', 'madera', 'pieza', '1.25', '2.50');
        $this->crearMaterial('Abeto', 'madera', 'pieza', '0.50', '1.00');
        $this->crearMaterial('Acabado', 'acabado', 'litro', '6.00', '3.00');
        $this->crearMaterial('Bisagra', 'herraje', 'pieza', '2.00', '2.00');
        $this->crearMaterial('Cero', 'herraje', 'pieza', '0.00', '0.00');

        $respuesta = $this->actingAs(User::factory()->create())
            ->get(route('dashboard'));

        $respuesta
            ->assertOk()
            ->assertSeeInOrder(['Abeto', 'Cedro', 'Bisagra', 'Acabado', 'Cero'])
            ->assertSee('0.50')
            ->assertSee('1.00')
            ->assertSee('1.25')
            ->assertSee('2.50')
            ->assertSee('6.00')
            ->assertSee('3.00')
            ->assertSee('Madera')
            ->assertSee('Herraje')
            ->assertSee('Acabado')
            ->assertSee('litro')
            ->assertSee('Bajo stock')
            ->assertSee('Disponible');

        $estadosPorMaterial = collect($respuesta->viewData('stockMateriales'))
            ->mapWithKeys(fn (array $fila): array => [$fila['material']->nombre => $fila['bajoStock']])
            ->all();

        $this->assertSame([
            'Abeto' => true,
            'Cedro' => true,
            'Bisagra' => true,
            'Acabado' => false,
            'Cero' => true,
        ], $estadosPorMaterial);
    }

    public function test_admin_e_inventario_ven_la_tabla_y_chofer_no_accede(): void
    {
        $this->crearMaterial('Pino visible', 'madera', 'pieza', '1.00', '2.00');

        foreach ([User::factory()->create(), User::factory()->encargadoInventario()->create()] as $usuario) {
            $this->actingAs($usuario)->get(route('dashboard'))
                ->assertOk()->assertSee('Stock actual contra mínimo')->assertSee('Pino visible');
        }

        $this->actingAs(User::factory()->chofer()->create())->get(route('dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->sinRol()->create())->get(route('dashboard'))->assertForbidden();
    }

    public function test_compara_relaciones_decimales_sin_perder_precision(): void
    {
        $this->crearMaterial('Aye mayor', 'madera', 'pieza', '99999999.98', '99999999.99');
        $this->crearMaterial('Zeta menor', 'madera', 'pieza', '99999999.97', '99999999.98');

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Zeta menor', 'Aye mayor']);
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
