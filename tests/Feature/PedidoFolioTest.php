<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoFolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_muestra_encabezado_y_folio_de_pedidos_activos_y_eliminados(): void
    {
        $this->actingAs(User::factory()->create());
        $activo = $this->crearPedido('Cliente activo');
        $eliminado = $this->crearPedido('Cliente eliminado');
        $eliminado->delete();

        $this->get(route('pedidos.index', ['estado' => 'todos']))
            ->assertOk()
            ->assertSee('<th>Folio</th>', false)
            ->assertSee("#{$activo->id}")
            ->assertSee("#{$eliminado->id}");
    }

    public function test_busqueda_por_folio_con_y_sin_numeral_encuentra_solo_el_pedido_indicado(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->crearPedido('Cliente buscado');
        $otroPedido = $this->crearPedido('Cliente excluido');

        foreach ([(string) $pedido->id, "#{$pedido->id}"] as $busqueda) {
            $this->get(route('pedidos.index', ['q' => $busqueda]))
                ->assertOk()
                ->assertSee('Cliente buscado')
                ->assertDontSee('Cliente excluido')
                ->assertSee('value="'.e($busqueda).'"', false);
        }

        $this->assertNotSame($pedido->id, $otroPedido->id);
    }

    public function test_busqueda_por_cliente_y_estado_sigue_funcionando(): void
    {
        $this->actingAs(User::factory()->create());
        $this->crearPedido('Mueblería Roble', 'pendiente');
        $this->crearPedido('Taller Cedro', 'completado');

        $this->get(route('pedidos.index', ['q' => 'Roble']))
            ->assertOk()
            ->assertSee('Mueblería Roble')
            ->assertDontSee('Taller Cedro');

        $this->get(route('pedidos.index', ['q' => 'completado']))
            ->assertOk()
            ->assertSee('Taller Cedro')
            ->assertDontSee('Mueblería Roble');
    }

    public function test_filtros_actuales_funcionan_junto_con_busqueda_por_folio(): void
    {
        $this->actingAs(User::factory()->create());
        $eliminado = $this->crearPedido('Pedido eliminado', 'completado');
        $eliminado->delete();
        $sinEntrega = $this->crearPedido('Pedido sin entrega', 'completado');
        $conEntrega = $this->crearPedido('Pedido con entrega', 'completado');
        $this->crearEntrega($conEntrega);

        $this->get(route('pedidos.index', [
            'estado' => 'eliminados',
            'estatus' => 'completado',
            'q' => "#{$eliminado->id}",
        ]))
            ->assertOk()
            ->assertSee('Pedido eliminado')
            ->assertDontSee('Pedido sin entrega');

        $this->get(route('pedidos.index', [
            'sin_entrega' => 1,
            'q' => "#{$sinEntrega->id}",
        ]))
            ->assertOk()
            ->assertSee('Pedido sin entrega')
            ->assertDontSee('Pedido con entrega');

        $this->get(route('pedidos.index', [
            'sin_entrega' => 1,
            'q' => "#{$conEntrega->id}",
        ]))
            ->assertOk()
            ->assertDontSee('Pedido con entrega')
            ->assertSee('No hay pedidos que coincidan');
    }

    public function test_resultados_vacios_muestran_mensaje_con_colspan_nuevo(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pedidos.index', ['q' => '#999999']))
            ->assertOk()
            ->assertSee('<td colspan="8" class="text-center text-muted py-4">No hay pedidos que coincidan con los filtros seleccionados.</td>', false);
    }

    private function crearPedido(string $nombreCliente, string $estado = 'pendiente'): Pedido
    {
        $cliente = Cliente::create([
            'nombre' => $nombreCliente,
            'telefono' => '555-0100',
        ]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => $estado,
            'total' => 1200,
            'direccion' => 'Dirección de prueba',
            'forma_pago' => 'Liquidado',
        ]);
    }

    private function crearEntrega(Pedido $pedido): Entrega
    {
        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => User::factory()->chofer()->create()->id,
            'fecha_asignada' => now()->toDateString(),
            'estado' => 'pendiente',
        ]);
    }
}
