<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntregaAsignacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_asigna_una_entrega_valida_y_queda_pendiente_sin_cambiar_pedido(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->pedido();
        $chofer = User::factory()->create(['rol' => 'chofer']);

        $this->post(route('entregas.store'), $this->datos($pedido, $chofer))
            ->assertRedirect(route('entregas.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('entregas', ['pedido_id' => $pedido->id, 'chofer_id' => $chofer->id, 'estado' => 'pendiente']);
        $this->assertSame('completado', $pedido->fresh()->estado);
    }

    public function test_formulario_de_creacion_no_lista_pedidos_pendientes_ni_en_proceso(): void
    {
        $this->actingAs(User::factory()->create());
        $pendiente = $this->pedido('pendiente');
        $enProceso = $this->pedido('en_proceso');
        $completado = $this->pedido();

        $response = $this->get(route('entregas.create'))->assertOk();
        $selectPedido = $this->selectPedido($response->getContent());

        $this->assertStringNotContainsString('value="'.$pendiente->id.'"', $selectPedido);
        $this->assertStringNotContainsString('value="'.$enProceso->id.'"', $selectPedido);
        $this->assertStringContainsString('value="'.$completado->id.'"', $selectPedido);
    }

    public function test_rechaza_pedido_pendiente_sin_crear_entrega(): void
    {
        $this->assertPedidoNoAsignable('pendiente');
    }

    public function test_rechaza_pedido_en_proceso_sin_crear_entrega(): void
    {
        $this->assertPedidoNoAsignable('en_proceso');
    }

    public function test_rechaza_pedidos_cancelados_eliminados_y_con_entrega_vigente(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->create(['rol' => 'chofer']);
        $cancelado = $this->pedido('cancelado');
        $this->post(route('entregas.store'), $this->datos($cancelado, $chofer))->assertSessionHasErrors('pedido_id');

        $eliminado = $this->pedido();
        $eliminado->delete();
        $this->post(route('entregas.store'), $this->datos($eliminado, $chofer))->assertSessionHasErrors('pedido_id');

        $ocupado = $this->pedido();
        $this->crearEntrega($ocupado, $chofer);
        $this->post(route('entregas.store'), $this->datos($ocupado, $chofer))->assertSessionHasErrors('pedido_id');
    }

    public function test_permite_asignar_pedido_completado_despues_de_incidencia(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->create(['rol' => 'chofer']);
        $pedido = $this->pedido();
        $this->crearEntrega($pedido, $chofer, Entrega::ESTADO_INCIDENCIA);

        $this->post(route('entregas.store'), $this->datos($pedido, $chofer))
            ->assertRedirect(route('entregas.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('entregas', 2);
    }

    public function test_rechaza_pedido_con_entrega_entregada(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->create(['rol' => 'chofer']);
        $pedido = $this->pedido();
        $this->crearEntrega($pedido, $chofer, Entrega::ESTADO_ENTREGADA);

        $this->post(route('entregas.store'), $this->datos($pedido, $chofer))
            ->assertSessionHasErrors(['pedido_id' => 'Solo se pueden asignar entregas a pedidos completados que no tengan una entrega vigente.']);

        $this->assertDatabaseCount('entregas', 1);
    }

    public function test_rechaza_usuario_que_no_es_chofer(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('entregas.store'), $this->datos($this->pedido(), User::factory()->create(['rol' => 'admin'])))
            ->assertSessionHasErrors('chofer_id');
    }

    public function test_rechaza_asignar_entrega_a_chofer_desactivado(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->pedido();
        $chofer = User::factory()->chofer()->inactivo()->create();

        $this->post(route('entregas.store'), $this->datos($pedido, $chofer))
            ->assertSessionHasErrors(['chofer_id' => 'Selecciona un chofer activo.']);

        $this->assertDatabaseMissing('entregas', ['pedido_id' => $pedido->id]);
    }

    public function test_formulario_de_creacion_lista_solo_choferes_activos(): void
    {
        $this->actingAs(User::factory()->create());
        User::factory()->chofer()->create(['name' => 'Chofer Activo']);
        User::factory()->chofer()->inactivo()->create(['name' => 'Chofer Desactivado']);

        $this->get(route('entregas.create'))
            ->assertOk()
            ->assertSee('Chofer Activo')
            ->assertDontSee('Chofer Desactivado');
    }

    public function test_editar_entrega_con_chofer_desactivado_exige_un_chofer_activo(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->pedido();
        $choferDesactivado = User::factory()->chofer()->inactivo()->create(['name' => 'Chofer anterior']);
        $choferActivo = User::factory()->chofer()->create(['name' => 'Chofer nuevo']);
        $entrega = $this->crearEntrega($pedido, $choferDesactivado);

        $this->get(route('entregas.edit', $entrega))
            ->assertOk()
            ->assertSee('Chofer nuevo')
            ->assertDontSee('Chofer anterior');

        $datos = [
            'chofer_id' => $choferDesactivado->id,
            'fecha_asignada' => now()->toDateString(),
        ];
        $this->put(route('entregas.update', $entrega), $datos)
            ->assertSessionHasErrors(['chofer_id' => 'Selecciona un chofer activo.']);

        $this->put(route('entregas.update', $entrega), [
            ...$datos,
            'chofer_id' => $choferActivo->id,
        ])->assertRedirect(route('entregas.index'))->assertSessionHasNoErrors();

        $this->assertSame($choferActivo->id, $entrega->fresh()->chofer_id);
    }

    public function test_filtro_de_entregas_incluye_y_marca_chofer_desactivado(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->inactivo()->create(['name' => 'Chofer Histórico']);
        $entrega = $this->crearEntrega($this->pedido(), $chofer);

        $this->get(route('entregas.index', ['chofer_id' => $chofer->id]))
            ->assertOk()
            ->assertSee('Chofer Histórico (desactivado)')
            ->assertSee('#'.$entrega->pedido_id);
    }

    public function test_lista_filtra_por_chofer_y_fecha(): void
    {
        $this->actingAs(User::factory()->create());
        $choferA = User::factory()->create(['rol' => 'chofer', 'name' => 'Chofer Uno']);
        $choferB = User::factory()->create(['rol' => 'chofer', 'name' => 'Chofer Dos']);
        $this->crearEntrega($this->pedidoConDireccion('Dirección del chofer Uno'), $choferA, 'pendiente', now()->toDateString());
        $this->crearEntrega($this->pedidoConDireccion('Dirección del chofer Dos'), $choferB, 'pendiente', now()->addDay()->toDateString());

        $this->get(route('entregas.index', ['chofer_id' => $choferA->id]))->assertOk()->assertSee('Dirección del chofer Uno')->assertDontSee('Dirección del chofer Dos');
        $this->get(route('entregas.index', ['fecha' => now()->addDay()->toDateString()]))->assertOk()->assertSee('Dirección del chofer Dos')->assertDontSee('Dirección del chofer Uno');
    }

    public function test_solo_edita_entregas_pendientes_y_no_cambia_el_pedido(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->create(['rol' => 'chofer']);
        $pedido = $this->pedido();
        $pendiente = $this->crearEntrega($pedido, $chofer);
        $otroChofer = User::factory()->create(['rol' => 'chofer']);

        $this->get(route('entregas.edit', $pendiente))
            ->assertOk()
            ->assertSee('Pedido #'.$pedido->id);

        $this->put(route('entregas.update', $pendiente), [
            'pedido_id' => $this->pedido()->id,
            'chofer_id' => $otroChofer->id,
            'fecha_asignada' => now()->toDateString(),
            'orden' => 2,
        ])->assertRedirect(route('entregas.index'))->assertSessionHasNoErrors();
        $this->assertSame($pedido->id, $pendiente->fresh()->pedido_id);
        $this->assertSame('completado', $pedido->fresh()->estado);

        $pendiente->cambiarEstado(Entrega::ESTADO_EN_CAMINO);
        $this->get(route('entregas.edit', $pendiente))->assertRedirect(route('entregas.index'))->assertSessionHas('error');
        $this->put(route('entregas.update', $pendiente), [
            'chofer_id' => $otroChofer->id,
            'fecha_asignada' => now()->toDateString(),
            'orden' => 3,
        ])->assertRedirect(route('entregas.index'))->assertSessionHas('error');
    }

    private function pedido(string $estado = 'completado'): Pedido
    {
        return $this->pedidoConDireccion('Calle de prueba 10', $estado);
    }

    private function pedidoConDireccion(string $direccion, string $estado = 'pendiente'): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba', 'telefono' => null]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'estado' => $estado,
            'total' => 100,
            'direccion' => $direccion,
        ]);
    }

    private function datos(Pedido $pedido, User $chofer): array
    {
        return ['pedido_id' => $pedido->id, 'chofer_id' => $chofer->id, 'fecha_asignada' => now()->toDateString()];
    }

    private function crearEntrega(Pedido $pedido, User $chofer, string $estado = 'pendiente', ?string $fecha = null): Entrega
    {
        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => $fecha ?? now()->toDateString(),
            'estado' => $estado,
        ]);
    }

    private function assertPedidoNoAsignable(string $estado): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->pedido($estado);
        $chofer = User::factory()->create(['rol' => 'chofer']);

        $this->post(route('entregas.store'), $this->datos($pedido, $chofer))
            ->assertSessionHasErrors(['pedido_id' => 'Solo se pueden asignar entregas a pedidos completados que no tengan una entrega vigente.']);

        $this->assertDatabaseMissing('entregas', ['pedido_id' => $pedido->id]);
    }

    private function selectPedido(string $content): string
    {
        preg_match('/<select name="pedido_id".*?<\/select>/s', $content, $coincidencia);
        $this->assertNotEmpty($coincidencia, 'No se encontró el selector de pedido.');

        return $coincidencia[0];
    }
}
