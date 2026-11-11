<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use App\Services\EvidenciaEntregaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RegistrarIncidenciaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_incidencia_desde_en_camino(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        $ahora = Carbon::parse('2035-11-10 12:00:00');
        Carbon::setTestNow($ahora);
        Sanctum::actingAs($entrega->chofer);

        try {
            $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => 'Cliente no localizado'])
                ->assertOk()
                ->assertJsonPath('data.estado', Entrega::ESTADO_INCIDENCIA);

            $entrega->refresh();
            $this->assertSame(Entrega::ESTADO_INCIDENCIA, $entrega->estado);
            $this->assertSame('Cliente no localizado', $entrega->incidencia_motivo);
            $this->assertSame($ahora->toDateTimeString(), $entrega->estado_fecha_hora->toDateTimeString());
            $this->assertNotSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_registra_incidencia_desde_pendiente(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        $entrega->update(['estado_fecha_hora' => now()->subDay()]);
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        Sanctum::actingAs($entrega->chofer);

        $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => 'Domicilio cerrado'])
            ->assertOk()
            ->assertJsonPath('data.estado', Entrega::ESTADO_INCIDENCIA);

        $this->assertDatabaseHas('entregas', [
            'id' => $entrega->id,
            'estado' => Entrega::ESTADO_INCIDENCIA,
            'incidencia_motivo' => 'Domicilio cerrado',
        ]);
        $this->assertNotSame($fechaAnterior, $entrega->fresh()->estado_fecha_hora->toDateTimeString());
    }

    public function test_rechaza_motivo_vacio_ausente_o_corto_sin_cambiar_la_entrega(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);

        $casos = [
            [[], 'El motivo es obligatorio.'],
            [['motivo' => ''], 'El motivo es obligatorio.'],
            [['motivo' => 'muy corto'], 'El motivo de la incidencia debe tener al menos 10 caracteres.'],
        ];

        foreach ($casos as [$datos, $mensaje]) {
            $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
            $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();

            $this->postJson("/api/entregas/{$entrega->id}/incidencia", $datos)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('motivo')
                ->assertJsonFragment([$mensaje]);

            $entrega->refresh();
            $this->assertSame(Entrega::ESTADO_EN_CAMINO, $entrega->estado);
            $this->assertNull($entrega->incidencia_motivo);
            $this->assertSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
        }
    }

    public function test_rechaza_motivo_demasiado_largo(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        Sanctum::actingAs($entrega->chofer);

        $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => str_repeat('a', 65536)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo')
            ->assertJsonFragment(['El motivo no debe exceder los 65535 caracteres.']);

        $this->assertSame(Entrega::ESTADO_PENDIENTE, $entrega->fresh()->estado);
        $this->assertNull($entrega->fresh()->incidencia_motivo);
    }

    public function test_no_expone_entregas_ajenas_inexistentes_o_de_pedidos_eliminados(): void
    {
        $chofer = User::factory()->chofer()->create();
        $ajena = $this->crearEntrega(User::factory()->chofer()->create());
        $eliminada = $this->crearEntrega($chofer);
        $eliminada->pedido->delete();
        Sanctum::actingAs($chofer);

        foreach ([$ajena, $eliminada] as $entrega) {
            $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => 'Cliente no localizado'])
                ->assertNotFound();
            $this->assertSame(Entrega::ESTADO_PENDIENTE, $entrega->fresh()->estado);
            $this->assertNull($entrega->fresh()->incidencia_motivo);
        }

        $this->postJson('/api/entregas/999999/incidencia', ['motivo' => 'Cliente no localizado'])
            ->assertNotFound();
    }

    public function test_rechaza_entrega_cerrada_sin_cambiarla(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);

        foreach ([Entrega::ESTADO_ENTREGADA, Entrega::ESTADO_INCIDENCIA] as $estado) {
            $entrega = $this->crearEntrega($chofer, $estado);
            $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();

            $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => 'Cliente no localizado'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('estado')
                ->assertJsonFragment(['Esta entrega ya fue cerrada y no puede cambiar de estado.']);

            $entrega->refresh();
            $this->assertSame($estado, $entrega->estado);
            $this->assertNull($entrega->incidencia_motivo);
            $this->assertSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
        }
    }

    public function test_rechaza_entrega_activa_con_evidencia_previa_y_revierte_la_operacion(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $entrega->update(['receptor_nombre' => 'Receptor previo']);
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        Sanctum::actingAs($entrega->chofer);

        $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => 'Cliente no localizado'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('evidencia')
            ->assertJsonFragment(['Esta entrega ya tiene evidencia registrada.']);

        $entrega->refresh();
        $this->assertSame(Entrega::ESTADO_EN_CAMINO, $entrega->estado);
        $this->assertNull($entrega->incidencia_motivo);
        $this->assertSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
    }

    public function test_ruta_requiere_chofer_activo_autenticado(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        $ruta = "/api/entregas/{$entrega->id}/incidencia";
        $datos = ['motivo' => 'Cliente no localizado'];

        $this->postJson($ruta, $datos)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($ruta, $datos)->assertForbidden()
            ->assertExactJson(['message' => 'Esta cuenta no tiene acceso a la app.']);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->chofer()->inactivo()->create());
        $this->postJson($ruta, $datos)->assertForbidden()
            ->assertExactJson(['message' => 'Tu cuenta está desactivada. Contacta a un administrador.']);

        $this->assertSame(Entrega::ESTADO_PENDIENTE, $entrega->fresh()->estado);
    }

    public function test_si_el_servicio_falla_la_transaccion_no_deja_cambios(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        Sanctum::actingAs($entrega->chofer);
        $this->mock(EvidenciaEntregaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrarIncidencia')->once()
                ->andReturnUsing(function (Entrega $entrega): never {
                    $entrega->update(['incidencia_motivo' => 'Cambio que debe revertirse']);
                    throw new RuntimeException('Fallo simulado del servicio.');
                });
        });

        $this->withoutExceptionHandling();

        try {
            $this->postJson("/api/entregas/{$entrega->id}/incidencia", ['motivo' => 'Cliente no localizado']);
            $this->fail('Se esperaba el fallo simulado del servicio.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado del servicio.', $exception->getMessage());
        }

        $entrega->refresh();
        $this->assertSame(Entrega::ESTADO_EN_CAMINO, $entrega->estado);
        $this->assertNull($entrega->incidencia_motivo);
        $this->assertSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
    }

    public function test_entrega_con_incidencia_deja_de_ser_activa_y_no_desplaza_el_orden(): void
    {
        $chofer = User::factory()->chofer()->create();
        $primera = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO, '2026-11-10', 1);
        $segunda = $this->crearEntrega($chofer, Entrega::ESTADO_PENDIENTE, '2026-11-10', 2);
        Sanctum::actingAs($chofer);

        $this->postJson("/api/entregas/{$primera->id}/incidencia", ['motivo' => 'Cliente no localizado'])
            ->assertOk();

        $this->assertSame(1, $primera->fresh()->orden);
        $this->assertSame(2, $segunda->fresh()->orden);
        $this->getJson('/api/entregas')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $segunda->id)
            ->assertJsonMissing(['id' => $primera->id]);
    }

    public function test_restringe_el_parametro_de_ruta_a_numeros(): void
    {
        Sanctum::actingAs(User::factory()->chofer()->create());

        $this->postJson('/api/entregas/no-numerico/incidencia', ['motivo' => 'Cliente no localizado'])
            ->assertNotFound();
    }

    private function crearEntrega(
        User $chofer,
        string $estado = Entrega::ESTADO_PENDIENTE,
        string $fecha = '2026-11-10',
        ?int $orden = 1,
    ): Entrega {
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
