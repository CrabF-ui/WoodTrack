<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidosSinHerrajesTest extends TestCase
{
    use RefreshDatabase;

    public function test_listado_sin_filtros_muestra_insignia_solo_en_completados_sin_herrajes(): void
    {
        $this->pedido('Completado sin herrajes', 'completado');
        $conHerrajes = $this->pedido('Completado con herrajes', 'completado');
        $conHerrajes->herrajes()->create(['material_id' => $this->material()->id, 'cantidad' => 1]);
        $this->pedido('Pedido pendiente', 'pendiente');

        $response = $this->actingAs(User::factory()->create())
            ->get(route('pedidos.index'))
            ->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '>Sin herrajes</span>'));
    }

    public function test_listado_muestra_insignia_y_filtro_sin_herrajes_combinado_con_estatus_y_busqueda(): void
    {
        $visible = $this->pedido('Roble sin herrajes', 'completado');
        $this->pedido('Cedro sin herrajes', 'completado');
        $conHerrajes = $this->pedido('Roble con herrajes', 'completado');
        $conHerrajes->herrajes()->create(['material_id' => $this->material()->id, 'cantidad' => 1]);
        $this->pedido('Roble pendiente', 'pendiente');

        $this->assertEquals([$visible->id, $this->pedidoId('Cedro sin herrajes')], Pedido::completadosSinHerrajes()->orderBy('id')->pluck('id')->all());

        $this->actingAs(User::factory()->create())
            ->get(route('pedidos.index', [
                'sin_herrajes' => 1,
                'estado' => 'activos',
                'estatus' => 'completado',
                'q' => 'Roble',
            ]))
            ->assertOk()
            ->assertSee('Roble sin herrajes')
            ->assertSee('Sin herrajes')
            ->assertDontSee('Cedro sin herrajes')
            ->assertDontSee('Roble con herrajes')
            ->assertDontSee('Roble pendiente')
            ->assertSee('Mostrando solo pedidos completados sin herrajes.')
            ->assertSee('Quitar filtro');
    }

    public function test_tarjeta_de_dashboard_de_completados_sin_herrajes_es_solo_para_admin(): void
    {
        $this->pedido('Sin herrajes', 'completado');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pedidos completados sin herrajes')
            ->assertSee(route('pedidos.index', ['sin_herrajes' => 1]), false);

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pedidos completados sin herrajes');
    }

    private function pedido(string $cliente, string $estado): Pedido
    {
        return Pedido::create([
            'cliente_id' => Cliente::create(['nombre' => $cliente])->id,
            'fecha_pedido' => now()->toDateString(),
            'estado' => $estado,
            'total' => 100,
            'direccion' => 'Dirección de prueba',
        ]);
    }

    private function pedidoId(string $cliente): int
    {
        return Pedido::whereHas('cliente', fn ($query) => $query->where('nombre', $cliente))->valueOrFail('id');
    }

    private function material(): Material
    {
        return Material::create([
            'nombre' => 'Bisagra',
            'tipo' => 'herraje',
            'unidad_medida' => 'pieza',
            'stock_actual' => 10,
            'stock_minimo' => 0,
        ]);
    }
}
