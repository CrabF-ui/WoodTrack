<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CambiarEstadoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pendiente_pasa_a_en_camino_y_actualiza_la_fecha(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        $fechaAnterior = $entrega->estado_fecha_hora;
        $ahora = Carbon::parse('2035-11-09 12:00:00');
        Carbon::setTestNow($ahora);
        Sanctum::actingAs($entrega->chofer);

        try {
            $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'en_camino'])->assertOk()
                ->assertJsonPath('data.estado', 'en_camino');

            $this->assertSame('en_camino', $entrega->fresh()->estado);
            $this->assertSame($ahora->toDateTimeString(), $entrega->fresh()->estado_fecha_hora->toDateTimeString());
            $this->assertNotEquals($fechaAnterior->toDateTimeString(), $entrega->fresh()->estado_fecha_hora->toDateTimeString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_en_camino_pasa_a_pendiente(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), 'en_camino');
        Sanctum::actingAs($entrega->chofer);

        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'pendiente'])->assertOk()
            ->assertJsonPath('data.estado', 'pendiente');
        $this->assertSame('pendiente', $entrega->fresh()->estado);
    }

    public function test_mismo_estado_es_idempotente_y_no_actualiza_fecha(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), 'pendiente');
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        Sanctum::actingAs($entrega->chofer);

        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'pendiente'])->assertOk()
            ->assertJsonPath('data.estado', 'pendiente');

        $this->assertSame($fechaAnterior, $entrega->fresh()->estado_fecha_hora->toDateTimeString());
    }

    public function test_entrega_cerrada_no_puede_cambiar_de_estado(): void
    {
        foreach (['entregada', 'incidencia'] as $estado) {
            $entrega = $this->crearEntrega(User::factory()->chofer()->create(), $estado);
            Sanctum::actingAs($entrega->chofer);

            $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'pendiente'])
                ->assertUnprocessable()->assertJsonValidationErrors('estado')
                ->assertJsonFragment(['Esta entrega ya fue cerrada y no puede cambiar de estado.']);
            $this->assertSame($estado, $entrega->fresh()->estado);
        }
    }

    public function test_solo_acepta_estados_pendiente_y_en_camino(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        Sanctum::actingAs($entrega->chofer);

        foreach (['entregada', 'incidencia', 'desconocido'] as $estado) {
            $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => $estado])
                ->assertUnprocessable()->assertJsonValidationErrors('estado')
                ->assertJsonFragment(['El estado debe ser pendiente o en_camino.']);
        }

        $this->patchJson("/api/entregas/{$entrega->id}/estado", [])->assertUnprocessable()
            ->assertJsonValidationErrors('estado')->assertJsonFragment(['El estado es obligatorio.']);
    }

    public function test_no_expone_entregas_de_otro_chofer_pedidos_eliminados_o_ids_inexistentes(): void
    {
        $chofer = User::factory()->chofer()->create();
        $otroChofer = User::factory()->chofer()->create();
        $ajena = $this->crearEntrega($otroChofer);
        $eliminada = $this->crearEntrega($chofer);
        $eliminada->pedido->delete();
        Sanctum::actingAs($chofer);

        $this->patchJson("/api/entregas/{$ajena->id}/estado", ['estado' => 'en_camino'])->assertNotFound();
        $this->patchJson("/api/entregas/{$eliminada->id}/estado", ['estado' => 'en_camino'])->assertNotFound();
        $this->patchJson('/api/entregas/999999/estado', ['estado' => 'en_camino'])->assertNotFound();
    }

    public function test_ruta_requiere_chofer_activo_autenticado(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'en_camino'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->chofer()->inactivo()->create());
        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'en_camino'])->assertForbidden()
            ->assertExactJson(['message' => 'Tu cuenta está desactivada. Contacta a un administrador.']);

        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'en_camino'])->assertForbidden()
            ->assertExactJson(['message' => 'Esta cuenta no tiene acceso a la app.']);
    }

    public function test_respuesta_usa_recurso_sin_datos_financieros(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        DetallePedido::create(['pedido_id' => $entrega->pedido_id, 'producto' => 'Banco', 'cantidad' => 2, 'precio_unitario' => 50]);
        Sanctum::actingAs($entrega->chofer);

        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'en_camino'])->assertOk()
            ->assertJsonStructure(['data' => [
                'id', 'fecha_asignada', 'orden', 'estado', 'estado_fecha_hora', 'notas',
                'pedido' => ['id', 'cliente', 'telefono', 'direccion', 'fecha_entrega', 'productos' => [['producto', 'cantidad']]],
            ]])
            ->assertJsonMissingPath('data.total')->assertJsonMissingPath('data.precio_unitario')
            ->assertJsonMissingPath('data.pedido.monto_anticipo')->assertJsonMissingPath('data.pedido.forma_pago')
            ->assertJsonMissingPath('data.pedido.herrajes');
    }

    public function test_transicion_no_modifica_otros_campos_de_entrega(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), 'pendiente', '2026-11-15', 4);
        $entrega->update(['notas' => 'Llamar antes de llegar']);
        $fecha = $entrega->fecha_asignada->toDateString();
        Sanctum::actingAs($entrega->chofer);

        $this->patchJson("/api/entregas/{$entrega->id}/estado", ['estado' => 'en_camino'])->assertOk();

        $entrega->refresh();
        $this->assertSame(4, $entrega->orden);
        $this->assertSame('Llamar antes de llegar', $entrega->notas);
        $this->assertSame($fecha, $entrega->fecha_asignada->toDateString());
    }

    public function test_permite_dos_entregas_en_camino_y_fechas_pasadas_o_futuras(): void
    {
        $chofer = User::factory()->chofer()->create();
        $pasada = $this->crearEntrega($chofer, 'pendiente', '2020-01-01');
        $futura = $this->crearEntrega($chofer, 'pendiente', '2030-12-31');
        Sanctum::actingAs($chofer);

        $this->patchJson("/api/entregas/{$pasada->id}/estado", ['estado' => 'en_camino'])->assertOk();
        $this->patchJson("/api/entregas/{$futura->id}/estado", ['estado' => 'en_camino'])->assertOk();

        $this->assertSame('en_camino', $pasada->fresh()->estado);
        $this->assertSame('en_camino', $futura->fresh()->estado);
    }

    public function test_restringe_el_parametro_de_ruta_a_numeros(): void
    {
        Sanctum::actingAs(User::factory()->chofer()->create());

        $this->patchJson('/api/entregas/no-numerico/estado', ['estado' => 'en_camino'])->assertNotFound();
    }

    private function crearEntrega(User $chofer, string $estado = 'pendiente', string $fecha = '2026-11-08', ?int $orden = 1): Entrega
    {
        $cliente = Cliente::create(['nombre' => 'Cliente API', 'telefono' => '5550100']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => $fecha,
            'fecha_entrega' => $fecha,
            'estado' => 'pendiente',
            'total' => 100,
            'direccion' => 'Calle API 10',
        ]);

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => $fecha,
            'orden' => $orden,
            'estado' => $estado,
        ]);
    }
}
