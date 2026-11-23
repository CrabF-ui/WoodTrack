<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardKpisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_ve_los_cuatro_indicadores_con_los_datos_de_ventas_del_mes(): void
    {
        $this->pedido('2026-10-10', 300, 'Cliente anterior', 'Banco', 2);
        $this->pedido('2026-11-02', 500, 'Muebles Luna', 'Mesa de roble', 5);
        $this->pedido('2026-11-05', 250, 'Muebles Luna', 'Silla', 2);
        $this->pedido('2026-11-06', 900, 'Cancelado', 'Armario', 9, 'cancelado');
        $eliminado = $this->pedido('2026-11-07', 800, 'Eliminado', 'Cómoda', 8);
        $eliminado->delete();

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Indicadores del mes')
            ->assertSee('noviembre 2026')
            ->assertSee('Ventas del mes')
            ->assertSee('$750.00')
            ->assertSee('2 pedidos')
            ->assertSee('▲ 150.0 % vs mes anterior')
            ->assertSee('Producto más vendido')
            ->assertSee('Mesa de roble')
            ->assertSee('5 unidades')
            ->assertSee('Ticket promedio')
            ->assertSee('$375.00')
            ->assertSee('▲ 25.0 % vs mes anterior')
            ->assertSee('Cliente principal')
            ->assertSee('Muebles Luna')
            ->assertDontSee('$1,550.00')
            ->assertSee('ventas-mes-chart', false)
            ->assertSee('productos-mes-chart', false)
            ->assertSee('clientes-mes-chart', false)
            ->assertSee('chart.js@4.4.7');
    }

    public function test_variaciones_son_nd_cuando_el_mes_anterior_no_tiene_ventas(): void
    {
        $this->pedido('2026-11-05', 250, 'Cliente nuevo', 'Mesa', 1);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('N/D vs mes anterior', false)
            ->assertSee('$250.00')
            ->assertSee('1 pedido');
    }

    public function test_admin_ve_estados_sin_datos_sin_canvases_rotos(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Sin ventas este mes')
            ->assertSee('Sin datos del mes')
            ->assertSee('Sin entregas hoy')
            ->assertDontSee('ventas-mes-chart', false)
            ->assertDontSee('productos-mes-chart', false)
            ->assertDontSee('clientes-mes-chart', false)
            ->assertDontSee('entregas-hoy-chart', false)
            ->assertDontSee('chart.js@4.4.7');
    }

    public function test_encargado_de_inventario_no_ve_indicadores_ni_graficas_nuevas(): void
    {
        $this->pedido('2026-11-05', 250, 'Cliente oculto', 'Mesa oculta', 1);

        $this->actingAs(User::factory()->encargadoInventario()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Indicadores del mes')
            ->assertDontSee('Gráficas')
            ->assertDontSee('ventas-mes-chart', false)
            ->assertDontSee('productos-mes-chart', false)
            ->assertDontSee('clientes-mes-chart', false)
            ->assertDontSee('Cliente oculto')
            ->assertViewMissing('indicadoresMes');
    }

    public function test_indicadores_aparecen_antes_de_las_tarjetas_operativas_y_las_graficas(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Indicadores del mes', 'Papelera', 'Entregas de hoy', 'Gráficas']);
    }

    private function pedido(
        string $fecha,
        float $total,
        string $cliente,
        string $producto,
        float $cantidad,
        string $estado = 'pendiente',
    ): Pedido {
        $clienteModel = Cliente::create(['nombre' => $cliente]);
        $pedido = Pedido::create([
            'cliente_id' => $clienteModel->id,
            'fecha_pedido' => $fecha,
            'direccion' => 'Calle de prueba',
            'estado' => $estado,
            'total' => $total,
        ]);
        $pedido->detalles()->create([
            'producto' => $producto,
            'cantidad' => $cantidad,
            'precio_unitario' => $total / max($cantidad, 1),
        ]);

        return $pedido;
    }
}
