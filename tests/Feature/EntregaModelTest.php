<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EntregaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_una_entrega_con_estado_pendiente_por_defecto(): void
    {
        $entrega = $this->createEntrega();

        $this->assertSame('pendiente', $entrega->estado);
        $this->assertNotNull($entrega->estado_fecha_hora);
    }

    public function test_un_pedido_puede_tener_varias_entregas_y_obtiene_la_mas_reciente(): void
    {
        $entrega = $this->createEntrega();
        $segundaEntrega = Entrega::create([
            'pedido_id' => $entrega->pedido_id,
            'chofer_id' => $entrega->chofer_id,
            'fecha_asignada' => now()->addDay()->toDateString(),
        ]);

        $pedido = Pedido::findOrFail($entrega->pedido_id);

        $this->assertCount(2, $pedido->entregas);
        $this->assertTrue($pedido->entregaActual->is($segundaEntrega));
    }

    public function test_cambiar_estado_actualiza_estado_y_fecha_y_rechaza_estado_invalido(): void
    {
        $entrega = $this->createEntrega();
        $antes = $entrega->estado_fecha_hora;

        $entrega->cambiarEstado('en_camino');

        $this->assertSame('en_camino', $entrega->fresh()->estado);
        $this->assertGreaterThanOrEqual($antes->getTimestamp(), $entrega->fresh()->estado_fecha_hora->getTimestamp());

        try {
            $entrega->cambiarEstado('estado_invalido');
            $this->fail('Se esperaba InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            $this->assertSame('en_camino', $entrega->fresh()->estado);
        }
    }

    public function test_funcionan_las_relaciones_de_entrega_pedido_y_chofer(): void
    {
        $entrega = $this->createEntrega();
        $pedido = $entrega->pedido;
        $chofer = $entrega->chofer;

        $this->assertInstanceOf(Pedido::class, $pedido);
        $this->assertInstanceOf(User::class, $chofer);
        $this->assertTrue($pedido->entregas->first()->is($entrega));
        $this->assertTrue($chofer->entregasAsignadas->first()->is($entrega));
    }

    private function createEntrega(): Entrega
    {
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'telefono' => null]);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'estado' => 'pendiente',
            'total' => 100,
        ]);
        $chofer = User::factory()->create(['rol' => 'chofer']);

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => now()->toDateString(),
        ]);
    }
}
