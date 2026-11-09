<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EntregasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_token_responde_401_en_json(): void
    {
        $this->getJson('/api/entregas')->assertUnauthorized();
    }

    public function test_admin_no_puede_acceder(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/entregas')->assertForbidden()
            ->assertExactJson(['message' => 'Esta cuenta no tiene acceso a la app.']);
    }

    public function test_chofer_inactivo_no_puede_acceder(): void
    {
        Sanctum::actingAs(User::factory()->chofer()->inactivo()->create());

        $this->getJson('/api/entregas')->assertForbidden()
            ->assertExactJson(['message' => 'Tu cuenta está desactivada. Contacta a un administrador.']);
    }

    public function test_chofer_solo_ve_sus_entregas(): void
    {
        $chofer = User::factory()->chofer()->create();
        $otro = User::factory()->chofer()->create();
        $propia = $this->crearEntrega($chofer);
        $ajena = $this->crearEntrega($otro);
        Sanctum::actingAs($chofer);

        $this->getJson('/api/entregas')->assertOk()
            ->assertJsonPath('data.0.id', $propia->id)
            ->assertJsonMissing(['id' => $ajena->id]);
    }

    public function test_filtros_de_fecha_estado_y_estados_por_defecto(): void
    {
        $chofer = User::factory()->chofer()->create();
        $pendiente = $this->crearEntrega($chofer, 'pendiente', '2026-11-08');
        $camino = $this->crearEntrega($chofer, 'en_camino', '2026-11-08');
        $entregada = $this->crearEntrega($chofer, 'entregada', '2026-11-08');
        $otraFecha = $this->crearEntrega($chofer, 'incidencia', '2026-11-09');
        Sanctum::actingAs($chofer);

        $this->getJson('/api/entregas')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $pendiente->id])->assertJsonFragment(['id' => $camino->id])
            ->assertJsonMissing(['id' => $entregada->id]);
        $this->getJson('/api/entregas?fecha=2026-11-08')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonMissing(['id' => $otraFecha->id]);
        $this->getJson('/api/entregas?estado=entregada')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $entregada->id);
    }

    public function test_parametros_invalidos_responden_422_con_mensajes_en_espanol(): void
    {
        Sanctum::actingAs(User::factory()->chofer()->create());

        $this->getJson('/api/entregas?fecha=08-11-2026')->assertUnprocessable()
            ->assertJsonValidationErrors('fecha')->assertJsonFragment(['La fecha debe tener el formato AAAA-MM-DD.']);
        $this->getJson('/api/entregas?estado=desconocido')->assertUnprocessable()
            ->assertJsonValidationErrors('estado')->assertJsonFragment(['El estado seleccionado no es válido.']);
    }

    public function test_orden_fecha_orden_nulo_al_final_y_id(): void
    {
        $chofer = User::factory()->chofer()->create();
        $later = $this->crearEntrega($chofer, 'pendiente', '2026-11-09', 1);
        $nullOrder = $this->crearEntrega($chofer, 'pendiente', '2026-11-08', null);
        $second = $this->crearEntrega($chofer, 'pendiente', '2026-11-08', 2);
        $first = $this->crearEntrega($chofer, 'pendiente', '2026-11-08', 1);
        Sanctum::actingAs($chofer);

        $this->getJson('/api/entregas')->assertOk()
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $nullOrder->id)->assertJsonPath('data.3.id', $later->id);
    }

    public function test_excluye_pedidos_eliminados(): void
    {
        $chofer = User::factory()->chofer()->create();
        $entrega = $this->crearEntrega($chofer);
        $entrega->pedido->delete();
        Sanctum::actingAs($chofer);

        $this->getJson('/api/entregas')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_recurso_cumple_el_contrato_y_omite_datos_financieros(): void
    {
        $chofer = User::factory()->chofer()->create();
        $entrega = $this->crearEntrega($chofer);
        $pedido = $entrega->pedido;
        DetallePedido::create(['pedido_id' => $pedido->id, 'producto' => 'Banco', 'cantidad' => 2, 'precio_unitario' => 50]);
        Sanctum::actingAs($chofer);

        $this->getJson('/api/entregas')->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'fecha_asignada', 'orden', 'estado', 'estado_fecha_hora', 'notas',
                    'pedido' => [
                        'id', 'cliente', 'telefono', 'direccion', 'fecha_entrega',
                        'productos' => [['producto', 'cantidad']],
                    ],
                ]],
            ])
            ->assertJsonMissingPath('data.0.total')->assertJsonMissingPath('data.0.precio_unitario')
            ->assertJsonMissingPath('data.0.pedido.monto_anticipo')->assertJsonMissingPath('data.0.pedido.forma_pago')
            ->assertJsonMissingPath('data.0.pedido.herrajes');
    }

    public function test_carga_entregas_con_consultas_eager_loaded(): void
    {
        $chofer = User::factory()->chofer()->create();
        $this->crearEntrega($chofer);
        $this->crearEntrega($chofer);
        $this->crearEntrega($chofer);
        Sanctum::actingAs($chofer);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/entregas')->assertOk();

        $entregaQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains(strtolower($query['query']), 'entregas'))->count();
        DB::disableQueryLog();
        $this->assertSame(1, $entregaQueries);
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
