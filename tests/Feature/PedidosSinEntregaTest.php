<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidosSinEntregaTest extends TestCase
{
    use RefreshDatabase;

    public function test_regla_identifica_pedidos_completados_sin_entregas_relevantes(): void
    {
        $sinEntregas = $this->crearPedido('Sin entregas', 'completado');
        $soloIncidencia = $this->crearPedido('Solo incidencia', 'completado');
        $this->crearEntrega($soloIncidencia, 'incidencia');

        $conPendiente = $this->crearPedido('Con pendiente', 'completado');
        $this->crearEntrega($conPendiente, 'pendiente');
        $conEnCamino = $this->crearPedido('Con envío', 'completado');
        $this->crearEntrega($conEnCamino, 'en_camino');
        $conEntregada = $this->crearPedido('Entregado', 'completado');
        $this->crearEntrega($conEntregada, 'entregada');

        foreach (['pendiente', 'en_proceso', 'cancelado'] as $estado) {
            $this->crearPedido('Estado '.$estado, $estado);
        }

        $eliminado = $this->crearPedido('Eliminado', 'completado');
        $eliminado->delete();

        $sinEntregas->load('entregas');

        $this->assertTrue($sinEntregas->estaCompletadoSinEntrega());
        $this->assertTrue($soloIncidencia->estaCompletadoSinEntrega());
        $this->assertFalse($conPendiente->estaCompletadoSinEntrega());
        $this->assertFalse($conEnCamino->estaCompletadoSinEntrega());
        $this->assertFalse($conEntregada->estaCompletadoSinEntrega());
        $this->assertFalse($eliminado->estaCompletadoSinEntrega());
        $this->assertEqualsCanonicalizing(
            [$sinEntregas->id, $soloIncidencia->id],
            Pedido::completadosSinEntrega()->pluck('id')->all(),
        );
    }

    public function test_dashboard_muestra_contador_y_enlace_solo_a_admin(): void
    {
        $this->crearPedido('Completado sin entrega', 'completado');
        $soloIncidencia = $this->crearPedido('Completado con incidencia', 'completado');
        $this->crearEntrega($soloIncidencia, 'incidencia');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Pedidos completados sin entrega', '<p class="display-6 mb-2">2</p>'], false)
            ->assertSee(route('pedidos.index', ['sin_entrega' => 1]), false);

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pedidos completados sin entrega');
    }

    public function test_dashboard_admin_muestra_estado_en_orden_cuando_no_hay_pedidos(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pedidos completados sin entrega')
            ->assertSee('Todo en orden');
    }

    public function test_filtro_sin_entrega_ignora_estado_y_estatus_y_respeta_busqueda(): void
    {
        $this->crearPedido('Roble sin entrega', 'completado');
        $this->crearPedido('Cedro sin entrega', 'completado');
        $notMatching = $this->crearPedido('Roble con entrega', 'completado');
        $this->crearEntrega($notMatching, 'pendiente');

        $this->actingAs(User::factory()->create())
            ->get(route('pedidos.index', [
                'sin_entrega' => 1,
                'estado' => 'eliminados',
                'estatus' => 'cancelado',
                'q' => 'Roble',
            ]))
            ->assertOk()
            ->assertSee('Roble sin entrega')
            ->assertDontSee('Cedro sin entrega')
            ->assertDontSee('Roble con entrega')
            ->assertSee('Mostrando solo pedidos completados sin entrega.')
            ->assertSee('Quitar filtro');
    }

    public function test_listado_sin_el_parametro_conserva_el_filtro_de_estatus(): void
    {
        $this->actingAs(User::factory()->create());
        $this->crearPedido('Pedido pendiente', 'pendiente');
        $this->crearPedido('Pedido completado', 'completado');

        $this->get(route('pedidos.index', ['estatus' => 'pendiente']))
            ->assertOk()
            ->assertSee('Pedido pendiente')
            ->assertDontSee('Pedido completado');
    }

    public function test_boton_asignar_entrega_solo_aparece_en_pedido_completado_sin_entrega(): void
    {
        $this->actingAs(User::factory()->create());
        $asignable = $this->crearPedido('Pedido asignable', 'completado');
        $conEntregaActiva = $this->crearPedido('Pedido ocupado', 'completado');
        $this->crearEntrega($conEntregaActiva, 'en_camino');
        $eliminado = $this->crearPedido('Pedido eliminado', 'completado');
        $eliminado->delete();

        $this->get(route('pedidos.index', ['estado' => 'todos']))
            ->assertOk()
            ->assertSee('href="'.route('entregas.create', ['pedido_id' => $asignable->id]).'"', false)
            ->assertDontSee('href="'.route('entregas.create', ['pedido_id' => $conEntregaActiva->id]).'"', false)
            ->assertDontSee('href="'.route('entregas.create', ['pedido_id' => $eliminado->id]).'"', false);
    }

    public function test_entregas_create_preselecciona_solo_pedidos_asignables(): void
    {
        $this->actingAs(User::factory()->create());
        $asignable = $this->crearPedido('Pedido asignable', 'completado');
        $this->crearPedido('Pedido previo', 'pendiente');
        $noAsignable = $this->crearPedido('Pedido ocupado', 'pendiente');
        $this->crearEntrega($noAsignable, 'pendiente');

        $asignableResponse = $this->get(route('entregas.create', ['pedido_id' => $asignable->id]))->assertOk();
        $this->assertStringContainsString(
            'value="'.$asignable->id.'" selected',
            $this->selectPedido($asignableResponse->getContent()),
        );

        $noAsignableResponse = $this->get(route('entregas.create', ['pedido_id' => $noAsignable->id]))
            ->assertOk()
            ->assertDontSee('is-invalid');
        $this->assertStringNotContainsString(' selected', $this->selectPedido($noAsignableResponse->getContent()));

        $invalidResponse = $this->get(route('entregas.create', ['pedido_id' => 'no-valido']))
            ->assertOk()
            ->assertDontSee('is-invalid');
        $this->assertStringNotContainsString(' selected', $this->selectPedido($invalidResponse->getContent()));
    }

    private function selectPedido(string $content): string
    {
        preg_match('/<select name="pedido_id".*?<\/select>/s', $content, $coincidencia);
        $this->assertNotEmpty($coincidencia, 'No se encontró el selector de pedido.');

        return $coincidencia[0];
    }

    private function crearPedido(string $nombreCliente, string $estado): Pedido
    {
        $cliente = Cliente::create(['nombre' => $nombreCliente]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'estado' => $estado,
            'total' => 100,
            'direccion' => 'Dirección de prueba',
        ]);
    }

    private function crearEntrega(Pedido $pedido, string $estado): Entrega
    {
        $chofer = User::factory()->chofer()->create();

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => now()->toDateString(),
            'estado' => $estado,
        ]);
    }
}
