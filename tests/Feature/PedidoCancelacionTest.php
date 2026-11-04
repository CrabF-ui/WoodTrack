<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PedidoHerraje;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoCancelacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelar_pedido_pendiente_devuelve_herrajes_y_elimina_entrega_pendiente(): void
    {
        $this->actingAs(User::factory()->create());
        [$pedido, $material] = $this->pedidoConHerraje();
        Entrega::create(['pedido_id' => $pedido->id, 'fecha_asignada' => now()->toDateString(), 'estado' => 'pendiente']);

        $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'cancelado'))
            ->assertRedirect(route('pedidos.index'))->assertSessionHasNoErrors();

        $this->assertSame('cancelado', $pedido->fresh()->estado);
        $this->assertEquals(10, $material->fresh()->stock_actual);
        $this->assertDatabaseHas('movimiento_inventarios', ['pedido_id' => $pedido->id, 'tipo' => 'entrada', 'motivo' => "Devolución por cancelación de pedido #{$pedido->id}"]);
        $this->assertDatabaseMissing('entregas', ['pedido_id' => $pedido->id]);
    }

    public function test_rechaza_cancelar_pedido_en_proceso_o_completado_sin_mover_stock(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['en_proceso', 'completado'] as $estado) {
            [$pedido, $material] = $this->pedidoConHerraje($estado);
            $movimientos = MovimientoInventario::count();
            $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'cancelado'))
                ->assertRedirect()->assertSessionHas('error', 'El pedido ya está en fabricación y no se puede cancelar.');
            $this->assertSame($estado, $pedido->fresh()->estado);
            $this->assertEquals(8, $material->fresh()->stock_actual);
            $this->assertSame($movimientos, MovimientoInventario::count());
        }
    }

    public function test_reactivar_pedido_cancelado_descuenta_herrajes_y_registra_salida(): void
    {
        $this->actingAs(User::factory()->create());
        [$pedido, $material] = $this->pedidoConHerraje('cancelado', 10);

        $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'pendiente'))->assertRedirect(route('pedidos.index'));

        $this->assertSame('pendiente', $pedido->fresh()->estado);
        $this->assertEquals(8, $material->fresh()->stock_actual);
        $this->assertDatabaseHas('movimiento_inventarios', ['pedido_id' => $pedido->id, 'tipo' => 'salida', 'motivo' => "Reactivación de pedido #{$pedido->id}"]);
    }

    public function test_reactivar_pedido_cancelado_sin_stock_no_cambia_nada(): void
    {
        $this->actingAs(User::factory()->create());
        [$pedido, $material] = $this->pedidoConHerraje('cancelado', 1);
        $antes = MovimientoInventario::count();

        $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'pendiente'))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('cancelado', $pedido->fresh()->estado);
        $this->assertEquals(1, $material->fresh()->stock_actual);
        $this->assertSame($antes, MovimientoInventario::count());
    }

    public function test_no_cancela_pedido_con_entregas_no_pendientes(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['en_camino', 'entregada', 'incidencia'] as $estadoEntrega) {
            [$pedido] = $this->pedidoConHerraje();
            Entrega::create(['pedido_id' => $pedido->id, 'fecha_asignada' => now()->toDateString(), 'estado' => $estadoEntrega]);
            $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'cancelado'))
                ->assertRedirect()->assertSessionHas('error', 'El pedido tiene una entrega que ya no está pendiente y no se puede cancelar.');
            $this->assertSame('pendiente', $pedido->fresh()->estado);
        }
    }

    public function test_editar_eliminar_y_restaurar_pedido_cancelado_no_mueve_stock(): void
    {
        $this->actingAs(User::factory()->create());
        [$pedido, $material] = $this->pedidoConHerraje('cancelado', 10);
        $conteo = MovimientoInventario::count();

        $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'cancelado'))->assertRedirect(route('pedidos.index'));
        $this->delete(route('pedidos.destroy', $pedido))->assertRedirect();
        $this->post(route('pedidos.restore', $pedido->id))->assertRedirect();

        $this->assertEquals(10, $material->fresh()->stock_actual);
        $this->assertSame($conteo, MovimientoInventario::count());
    }

    public function test_no_permite_sacar_pedido_cancelado_de_cancelacion_a_otro_estado(): void
    {
        $this->actingAs(User::factory()->create());
        [$pedido] = $this->pedidoConHerraje('cancelado', 10);
        $this->put(route('pedidos.update', $pedido), $this->payload($pedido, 'completado'))
            ->assertRedirect()->assertSessionHas('error', 'Un pedido cancelado solo puede volver al estado pendiente.');
        $this->assertSame('cancelado', $pedido->fresh()->estado);
    }

    private function pedidoConHerraje(string $estado = 'pendiente', float $stock = 8): array
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba', 'telefono' => null]);
        $material = Material::create(['nombre' => 'Bisagra', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => $stock, 'stock_minimo' => 0]);
        $pedido = Pedido::create(['cliente_id' => $cliente->id, 'fecha_pedido' => now()->toDateString(), 'fecha_entrega' => now()->addWeek()->toDateString(), 'estado' => $estado, 'total' => 100, 'direccion' => 'Calle de prueba']);
        $pedido->detalles()->create(['producto' => 'Escritorio', 'cantidad' => 1, 'precio_unitario' => 100]);
        PedidoHerraje::create(['pedido_id' => $pedido->id, 'material_id' => $material->id, 'cantidad' => 2]);
        return [$pedido, $material];
    }

    private function payload(Pedido $pedido, string $estado): array
    {
        return [
            'cliente_id' => $pedido->cliente_id,
            'fecha_pedido' => $pedido->fecha_pedido->toDateString(),
            'fecha_entrega' => $pedido->fecha_entrega->toDateString(),
            'estado' => $estado,
            'direccion' => $pedido->direccion,
            'detalles' => [['producto' => 'Escritorio', 'cantidad' => 1, 'precio_unitario' => 100]],
            'herrajes' => [['material_id' => $pedido->herrajes()->value('material_id'), 'cantidad' => 2]],
            'total' => 100,
            'forma_pago' => 'Liquidado',
        ];
    }
}
