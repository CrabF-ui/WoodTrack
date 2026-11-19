<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntregaReasignacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_de_pedidos_distingue_asignacion_reasignacion_y_pedidos_no_asignables(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create();
        $nuevo = $this->pedido('Nuevo');
        $incidencia = $this->pedido('Con incidencia');
        $this->entrega($incidencia, $chofer, Entrega::ESTADO_INCIDENCIA);

        foreach ([Entrega::ESTADO_PENDIENTE, Entrega::ESTADO_EN_CAMINO, Entrega::ESTADO_ENTREGADA] as $estado) {
            $pedido = $this->pedido('Con '.$estado);
            $this->entrega($pedido, $chofer, $estado);
        }

        $response = $this->get(route('pedidos.index'))->assertOk();
        $response->assertSee('href="'.route('entregas.create', ['pedido_id' => $nuevo->id]).'"', false)
            ->assertSee('Asignar entrega')
            ->assertSee('href="'.route('entregas.create', ['pedido_id' => $incidencia->id]).'"', false)
            ->assertSee('>Reasignar</a>', false)
            ->assertSee('Incidencia previa')
            ->assertSee('class="text-nowrap">#'.$incidencia->id, false)
            ->assertSee('d-flex flex-wrap gap-2 justify-content-end', false);

        foreach (Pedido::query()->where('id', '>', $incidencia->id)->pluck('id') as $pedidoId) {
            $response->assertDontSee('href="'.route('entregas.create', ['pedido_id' => $pedidoId]).'"', false);
        }
    }

    public function test_create_muestra_datos_de_incidencia_previa_y_marca_reasignaciones(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create();
        $nuevo = $this->pedido('Nuevo');
        $incidencia = $this->pedido('Con incidencia');
        $this->entrega($incidencia, $chofer, Entrega::ESTADO_INCIDENCIA, '2026-11-10', 'Domicilio cerrado');

        $this->get(route('entregas.create', ['pedido_id' => $incidencia->id]))
            ->assertOk()
            ->assertSee('Incidencia previa.')
            ->assertSee('10/11/2026')
            ->assertSee('Domicilio cerrado')
            ->assertSee('(reasignación)')
            ->assertSee('Reasignar entrega');

        $this->get(route('entregas.create', ['pedido_id' => $nuevo->id]))
            ->assertOk()
            ->assertDontSee('Incidencia previa.')
            ->assertSee('Asignar entrega');
    }

    public function test_reasignacion_crea_nuevo_intento_y_oculta_el_boton_del_pedido(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create();
        $pedido = $this->pedido('Reasignable');
        $this->entrega($pedido, $chofer, Entrega::ESTADO_INCIDENCIA);

        $this->post(route('entregas.store'), [
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => now()->toDateString(),
        ])->assertRedirect(route('entregas.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('entregas', 2);
        $this->assertDatabaseHas('entregas', ['pedido_id' => $pedido->id, 'estado' => Entrega::ESTADO_PENDIENTE]);
        $this->get(route('pedidos.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('entregas.create', ['pedido_id' => $pedido->id]).'"', false);
    }

    public function test_listado_muestra_una_fila_vigente_y_enlace_al_historial(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create(['name' => 'Chofer Historial']);
        $pedido = $this->pedido('Dos intentos', 'Dirección única');
        $this->entrega($pedido, $chofer, Entrega::ESTADO_INCIDENCIA, '2026-11-10', 'No había nadie');
        $this->entrega($pedido, $chofer, Entrega::ESTADO_PENDIENTE, '2026-11-11');

        $response = $this->get(route('entregas.index'))->assertOk();
        $response->assertSee('Ver intentos (2)')
            ->assertSee('href="'.route('entregas.intentos', $pedido).'"', false)
            ->assertDontSee('No había nadie');
        $this->assertSame(1, substr_count($response->getContent(), '<td>#'.$pedido->id.'</td>'));
        $this->assertMatchesRegularExpression('/<td>2<\/td>\s*<td>\s*<span class="badge/', $response->getContent());
    }

    public function test_un_solo_intento_no_muestra_historial_e_incidencia_vigente_requiere_accion(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create();
        $pedido = $this->pedido('Incidencia única');
        $this->entrega($pedido, $chofer, Entrega::ESTADO_INCIDENCIA, null, 'Acceso bloqueado');

        $this->get(route('entregas.index'))
            ->assertOk()
            ->assertDontSee('Ver intentos')
            ->assertSee('table-danger', false)
            ->assertSee('Requiere acción')
            ->assertSee('href="'.route('entregas.create', ['pedido_id' => $pedido->id]).'"', false);
    }

    public function test_vista_individual_lista_intentos_recientes_primero_con_estados_y_evidencia(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create(['name' => 'Chofer del historial']);
        $pedido = $this->pedido('Historial individual');
        $primero = $this->entrega($pedido, $chofer, Entrega::ESTADO_INCIDENCIA, '2026-11-10', 'Cliente ausente');
        $segundo = $this->entrega($pedido, $chofer, Entrega::ESTADO_ENTREGADA, '2026-11-11');

        $response = $this->get(route('entregas.intentos', $pedido))->assertOk();
        $response->assertSee('Cliente ausente')
            ->assertSee('Incidencia')
            ->assertSee('Entregada')
            ->assertSee(route('entregas.evidencia', $primero))
            ->assertSee(route('entregas.evidencia', $segundo))
            ->assertDontSee('Reasignar entrega')
            ->assertSeeInOrder(['11/11/2026', '10/11/2026']);
        $this->assertMatchesRegularExpression('/<td>2<\/td>\s*<td>11\/11\/2026/', $response->getContent());
        $this->assertMatchesRegularExpression('/<td>1<\/td>\s*<td>10\/11\/2026/', $response->getContent());
    }

    public function test_vista_individual_ofrece_reasignar_solo_si_el_ultimo_intento_es_incidencia(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create();
        $reasignable = $this->pedido('Reasignable desde historial');
        $this->entrega($reasignable, $chofer, Entrega::ESTADO_INCIDENCIA);
        $vigente = $this->pedido('Con entrega vigente');
        $this->entrega($vigente, $chofer, Entrega::ESTADO_INCIDENCIA, now()->subDay()->toDateString());
        $this->entrega($vigente, $chofer, Entrega::ESTADO_PENDIENTE);

        $this->get(route('entregas.intentos', $reasignable))
            ->assertOk()
            ->assertSee('href="'.route('entregas.create', ['pedido_id' => $reasignable->id]).'"', false);
        $this->get(route('entregas.intentos', $vigente))
            ->assertOk()
            ->assertDontSee('Reasignar entrega');
    }

    public function test_vista_individual_valida_pedido_entregas_y_conserva_filtros_al_volver(): void
    {
        $this->actingAs(User::factory()->create());
        $chofer = User::factory()->chofer()->create();
        $conEntregas = $this->pedido('Con filtros');
        $this->entrega($conEntregas, $chofer, Entrega::ESTADO_PENDIENTE, '2026-11-11');
        $sinEntregas = $this->pedido('Sin entregas');
        $filtros = ['chofer_id' => $chofer->id, 'fecha' => '2026-11-11'];

        $this->get(route('entregas.intentos', ['pedido' => $conEntregas->id, ...$filtros]))
            ->assertOk()
            ->assertSee(route('entregas.index', $filtros));
        $this->get(route('entregas.intentos', 999999))->assertNotFound();
        $this->get(route('entregas.intentos', $sinEntregas))
            ->assertRedirect(route('entregas.index'))
            ->assertSessionHas('error', 'Este pedido no tiene entregas registradas.');
    }

    public function test_filtros_se_aplican_solo_sobre_el_intento_vigente(): void
    {
        $this->actingAs(User::factory()->create());
        $choferAnterior = User::factory()->chofer()->create(['name' => 'Chofer Anterior']);
        $choferVigente = User::factory()->chofer()->create(['name' => 'Chofer Vigente']);
        $pedido = $this->pedido('Filtrado', 'Dirección filtrada');
        $this->entrega($pedido, $choferAnterior, Entrega::ESTADO_INCIDENCIA, '2026-11-10');
        $this->entrega($pedido, $choferVigente, Entrega::ESTADO_PENDIENTE, '2026-11-11');

        $this->get(route('entregas.index', ['chofer_id' => $choferAnterior->id]))
            ->assertOk()->assertDontSee('Dirección filtrada');
        $this->get(route('entregas.index', ['chofer_id' => $choferVigente->id, 'fecha' => '2026-11-11']))
            ->assertOk()->assertSee('Dirección filtrada');
        $this->get(route('entregas.index', ['fecha' => '2026-11-10']))
            ->assertOk()->assertDontSee('Dirección filtrada');
    }

    private function pedido(string $cliente, string $direccion = 'Calle de prueba'): Pedido
    {
        return Pedido::create([
            'cliente_id' => Cliente::create(['nombre' => $cliente])->id,
            'fecha_pedido' => now()->toDateString(),
            'estado' => 'completado',
            'total' => 100,
            'direccion' => $direccion,
        ]);
    }

    private function entrega(Pedido $pedido, User $chofer, string $estado, ?string $fecha = null, ?string $motivo = null): Entrega
    {
        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => $fecha ?? now()->toDateString(),
            'estado' => $estado,
            'incidencia_motivo' => $motivo,
        ]);
    }
}
