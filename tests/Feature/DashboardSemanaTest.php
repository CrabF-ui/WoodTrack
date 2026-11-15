<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardSemanaTest extends TestCase
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

    public function test_admin_ve_entregas_de_los_ultimos_siete_dias_con_los_limites_correctos(): void
    {
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->subDays(6)->toDateString());
        $this->crearEntrega(Entrega::ESTADO_PENDIENTE, now()->toDateString());
        $this->crearEntrega(Entrega::ESTADO_EN_CAMINO, now()->toDateString());
        $this->crearEntrega(Entrega::ESTADO_INCIDENCIA, now()->toDateString());
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->toDateString());
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->subDays(7)->toDateString());
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->addDay()->toDateString());

        $pedidoEliminado = $this->crearPedido();
        $this->crearEntrega(Entrega::ESTADO_ENTREGADA, now()->toDateString(), $pedidoEliminado);
        $pedidoEliminado->delete();

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Entregas de la semana')
            ->assertSee('2 entregadas de 5 asignadas')
            ->assertSee('Últimos 7 días');
    }

    public function test_admin_ve_la_suma_de_pedidos_registrados_excluyendo_cancelados_eliminados_y_fuera_de_fecha(): void
    {
        $this->crearPedido(now()->subDays(6)->toDateString(), 'pendiente', '100.50');
        $this->crearPedido(now()->toDateString(), 'completado', '200.00');
        $this->crearPedido(now()->toDateString(), 'cancelado', '999.00');

        $pedidoEliminado = $this->crearPedido(now()->toDateString(), 'pendiente', '500.00');
        $pedidoEliminado->delete();
        $this->crearPedido(now()->subDays(7)->toDateString(), 'pendiente', '700.00');

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pedidos registrados')
            ->assertSee('$300.50')
            ->assertSee('Últimos 7 días');
    }

    public function test_admin_ve_cero_cuando_no_hay_pedidos_en_la_semana(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pedidos registrados')
            ->assertSee('$0.00');
    }

    public function test_encargado_de_inventario_no_recibe_datos_ni_tarjetas_de_la_semana(): void
    {
        $this->crearPedido(now()->toDateString(), 'pendiente', '12345.00');

        $this->actingAs(User::factory()->encargadoInventario()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Entregas de la semana')
            ->assertDontSee('Pedidos registrados')
            ->assertDontSee('$12,345.00')
            ->assertViewMissing('entregasSemana')
            ->assertViewMissing('pedidosSemana');
    }

    private function crearPedido(?string $fecha = null, string $estado = 'completado', string $total = '100.00'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba']);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => $fecha ?? now()->toDateString(),
            'estado' => $estado,
            'total' => $total,
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
