<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardEntregasTest extends TestCase
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

    public function test_admin_ve_el_conteo_de_hoy_y_excluye_otras_fechas_y_pedidos_eliminados(): void
    {
        foreach ([Entrega::ESTADO_ENTREGADA, Entrega::ESTADO_PENDIENTE, Entrega::ESTADO_EN_CAMINO, Entrega::ESTADO_INCIDENCIA] as $estado) {
            $this->crearEntrega($estado, now()->toDateString());
        }
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->subDay()->toDateString());
        $this->crearEntrega(Entrega::ESTADO_PENDIENTE, now()->addDay()->toDateString());
        $pedidoEliminado = $this->crearPedido();
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->toDateString(), $pedidoEliminado);
        $pedidoEliminado->delete();

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('entregas-hoy-chart', false)
            ->assertSee('Entregadas: 1')
            ->assertSee('Pendientes: 3')
            ->assertSee('chart.js@4.4.7');
    }

    public function test_inventario_no_recibe_datos_ni_vista_de_dona_y_admin_ve_mensaje_sin_entregas(): void
    {
        $this->actingAs(User::factory()->encargadoInventario()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('entregas-hoy-chart', false)
            ->assertDontSee('chart.js', false)
            ->assertViewMissing('entregasHoy');

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No hay entregas programadas para hoy.')
            ->assertDontSee('entregas-hoy-chart', false)
            ->assertDontSee('chart.js', false);
    }

    private function crearPedido(): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba']);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'estado' => 'completado',
            'total' => '100.00',
        ]);
    }

    private function crearEntrega(string $estado, string $fecha, ?Pedido $pedido = null): Entrega
    {
        $pedido ??= $this->crearPedido();

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => User::factory()->chofer()->create()->id,
            'fecha_asignada' => $fecha,
            'estado' => $estado,
        ]);
    }
}
