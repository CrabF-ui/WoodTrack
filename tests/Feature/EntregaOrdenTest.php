<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntregaOrdenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['rol' => User::ROL_ADMIN]));
    }

    public function test_inserta_en_medio_y_muestra_aviso_plural(): void
    {
        [$chofer, $pedidos] = $this->prepararLista();
        $nuevoPedido = $this->pedido();
        $respuesta = $this->post(route('entregas.store'), $this->datos($nuevoPedido, $chofer, 2));

        $respuesta->assertSessionHas('success', 'Entrega asignada correctamente. Se recorrieron 2 entregas.');
        $this->assertSame([1, 3, 4], Entrega::whereIn('pedido_id', $pedidos->pluck('id'))->orderBy('orden')->pluck('orden')->all());
        $this->assertSame(2, Entrega::where('pedido_id', $nuevoPedido->id)->value('orden'));
    }

    public function test_orden_libre_o_al_final_no_desplaza(): void
    {
        [$chofer] = $this->prepararLista();
        $this->post(route('entregas.store'), $this->datos($this->pedido(), $chofer, 8))
            ->assertSessionHas('success', 'Entrega asignada correctamente.');
    }

    public function test_orden_libre_en_medio_no_desplaza(): void
    {
        $chofer = User::factory()->chofer()->create();
        $primera = $this->crearEntrega($this->pedido(), $chofer, 1);
        $tercera = $this->crearEntrega($this->pedido(), $chofer, 3);
        $nueva = $this->pedido();

        $this->post(route('entregas.store'), $this->datos($nueva, $chofer, 2))
            ->assertSessionHas('success', 'Entrega asignada correctamente.');

        $this->assertSame([1, 2, 3], Entrega::whereIn('pedido_id', [$primera->pedido_id, $nueva->id, $tercera->pedido_id])->orderBy('orden')->pluck('orden')->all());
    }

    public function test_orden_vacio_no_desplaza_y_se_guarda_nulo(): void
    {
        [$chofer] = $this->prepararLista();
        $this->post(route('entregas.store'), $this->datos($this->pedido(), $chofer, null))
            ->assertSessionHas('success', 'Entrega asignada correctamente.');
        $this->assertNull(Entrega::latest('id')->first()->orden);
    }

    public function test_editar_a_posicion_ocupada_excluye_la_entrega_editada(): void
    {
        [$chofer, $pedidos] = $this->prepararLista();
        $respuesta = $this->put(route('entregas.update', $pedidos[0]->entregas()->first()), [
            'chofer_id' => $chofer->id, 'fecha_asignada' => now()->toDateString(), 'orden' => 2,
        ]);
        $respuesta->assertSessionHas('success', 'Entrega actualizada correctamente. Se recorrieron 2 entregas.');
        $this->assertSame(3, Entrega::where('pedido_id', $pedidos[1]->id)->value('orden'));
        $this->assertSame(2, Entrega::where('pedido_id', $pedidos[0]->id)->value('orden'));
        $this->assertSame(4, Entrega::where('pedido_id', $pedidos[2]->id)->value('orden'));
    }

    public function test_editar_a_orden_libre_no_desplaza(): void
    {
        [$chofer, $pedidos] = $this->prepararLista();
        $entrega = Entrega::where('pedido_id', $pedidos[0]->id)->first();

        $this->put(route('entregas.update', $entrega), [
            'chofer_id' => $chofer->id, 'fecha_asignada' => now()->toDateString(), 'orden' => 8,
        ])->assertSessionHas('success', 'Entrega actualizada correctamente.');
        $this->assertSame(8, $entrega->fresh()->orden);
        $this->assertSame([2, 3], Entrega::whereIn('pedido_id', $pedidos->slice(1)->pluck('id'))->orderBy('orden')->pluck('orden')->all());
    }

    public function test_editar_sin_cambios_de_destino_no_desplaza(): void
    {
        [$chofer, $pedidos] = $this->prepararLista();
        $entrega = Entrega::where('pedido_id', $pedidos[0]->id)->first();
        $this->put(route('entregas.update', $entrega), [
            'chofer_id' => $chofer->id, 'fecha_asignada' => now()->toDateString(), 'orden' => 1,
        ])->assertSessionHas('success', 'Entrega actualizada correctamente.');
        $this->assertSame([1, 2, 3], Entrega::whereIn('pedido_id', $pedidos->pluck('id'))->orderBy('orden')->pluck('orden')->all());
    }

    public function test_solo_desplaza_entregas_activas_de_chofer_y_fecha_destino(): void
    {
        [$chofer, $pedidos] = $this->prepararLista();
        $otroChofer = User::factory()->chofer()->create();
        $otras = [
            $this->crearEntrega($this->pedido(), $otroChofer, 2),
            $this->crearEntrega($this->pedido(), $chofer, 2, now()->addDay()->toDateString()),
            $this->crearEntrega($this->pedido(), $chofer, 2, null, Entrega::ESTADO_ENTREGADA),
            $this->crearEntrega($this->pedido(), $chofer, 2, null, Entrega::ESTADO_INCIDENCIA),
        ];
        $this->post(route('entregas.store'), $this->datos($this->pedido(), $chofer, 2));
        foreach ($otras as $entrega) {
            $this->assertSame(2, $entrega->fresh()->orden);
        }
    }

    public function test_lista_web_muestra_ordenes_nulos_al_final(): void
    {
        $chofer = User::factory()->chofer()->create();
        $sinOrden = $this->crearEntrega($this->pedido(), $chofer, null);
        $conOrden = $this->crearEntrega($this->pedido(), $chofer, 1);
        $respuesta = $this->get(route('entregas.index'))->assertOk();
        $this->assertLessThan(strpos($respuesta->getContent(), '#'.$sinOrden->pedido_id), strpos($respuesta->getContent(), '#'.$conOrden->pedido_id));
    }

    private function prepararLista(): array
    {
        $chofer = User::factory()->chofer()->create();
        $pedidos = collect();
        foreach ([1, 2, 3] as $orden) {
            $pedido = $this->pedido();
            $pedidos->push($pedido);
            $this->crearEntrega($pedido, $chofer, $orden);
        }

        return [$chofer, $pedidos];
    }

    private function pedido(): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba', 'telefono' => null]);

        return Pedido::create(['cliente_id' => $cliente->id, 'fecha_pedido' => now()->toDateString(), 'estado' => 'pendiente', 'total' => 100, 'direccion' => 'Calle de prueba']);
    }

    private function datos(Pedido $pedido, User $chofer, ?int $orden): array
    {
        return ['pedido_id' => $pedido->id, 'chofer_id' => $chofer->id, 'fecha_asignada' => now()->toDateString(), 'orden' => $orden];
    }

    private function crearEntrega(Pedido $pedido, User $chofer, ?int $orden, ?string $fecha = null, string $estado = Entrega::ESTADO_PENDIENTE): Entrega
    {
        return Entrega::create(['pedido_id' => $pedido->id, 'chofer_id' => $chofer->id, 'fecha_asignada' => $fecha ?? now()->toDateString(), 'orden' => $orden, 'estado' => $estado]);
    }
}
