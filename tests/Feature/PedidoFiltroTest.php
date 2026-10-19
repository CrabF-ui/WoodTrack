<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoFiltroTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_active_deleted_and_all_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $active = $this->createOrder('Cliente activo');
        $deleted = $this->createOrder('Cliente eliminado');
        $deleted->delete();

        $this->get(route('pedidos.index', ['estado' => 'activos']))
            ->assertOk()
            ->assertSee('id="listado-pedidos"', false)
            ->assertSee('Cliente activo')
            ->assertDontSee('Cliente eliminado');

        $this->get(route('pedidos.index', ['estado' => 'eliminados']))
            ->assertOk()
            ->assertSee('Cliente eliminado')
            ->assertDontSee('Cliente activo');

        $this->get(route('pedidos.index', ['estado' => 'todos']))
            ->assertOk()
            ->assertSee('Cliente activo')
            ->assertSee('Cliente eliminado');
    }

    public function test_it_searches_orders_by_client_name(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createOrder('Mueblería Roble');
        $this->createOrder('Taller Cedro');

        $this->get(route('pedidos.index', ['q' => 'Roble']))
            ->assertOk()
            ->assertSee('Mueblería Roble')
            ->assertDontSee('Taller Cedro');
    }

    public function test_it_filters_by_each_order_status_and_ignores_invalid_status(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (Pedido::ESTADOS as $estado) {
            $this->createOrder('Cliente '.$estado, $estado);
        }

        foreach (Pedido::ESTADOS as $estado) {
            $response = $this->get(route('pedidos.index', ['estatus' => $estado]))
                ->assertOk()
                ->assertSee('Cliente '.$estado);

            foreach (Pedido::ESTADOS as $otroEstado) {
                if ($otroEstado !== $estado) {
                    $response->assertDontSee('Cliente '.$otroEstado);
                }
            }
        }

        $this->get(route('pedidos.index', ['estatus' => 'desconocido']))
            ->assertOk()
            ->assertSee('Cliente pendiente')
            ->assertSee('Cliente en_proceso');
    }

    public function test_status_filter_combines_with_search_and_deleted_listing(): void
    {
        $this->actingAs(User::factory()->create());
        $matching = $this->createOrder('Roble especial', Pedido::ESTADOS[1]);
        $matching->delete();
        $this->createOrder('Roble activo', Pedido::ESTADOS[1]);
        $this->createOrder('Cedro eliminado', Pedido::ESTADOS[1])->delete();

        $this->get(route('pedidos.index', [
            'estado' => 'eliminados',
            'estatus' => Pedido::ESTADOS[1],
            'q' => 'Roble',
        ]))
            ->assertOk()
            ->assertSee('Roble especial')
            ->assertDontSee('Roble activo')
            ->assertDontSee('Cedro eliminado');
    }

    public function test_restoring_an_order_returns_it_to_the_active_listing(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->createOrder('Cliente por restaurar');
        $pedido->delete();

        $this->post(route('pedidos.restore', [
            'id' => $pedido->id,
            'estado' => 'eliminados',
            'q' => 'Cliente',
        ]))
            ->assertRedirect(route('pedidos.index', ['estado' => 'eliminados', 'q' => 'Cliente']))
            ->assertSessionHas('success');

        $this->get(route('pedidos.index', ['estado' => 'activos']))
            ->assertOk()
            ->assertSee('Cliente por restaurar');
    }

    private function createOrder(string $clientName, string $status = 'pendiente'): Pedido
    {
        $cliente = Cliente::create([
            'nombre' => $clientName,
            'telefono' => '555-0100',
        ]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => $status,
            'total' => 1200,
            'direccion' => 'Dirección de prueba',
            'forma_pago' => 'Liquidado',
        ]);
    }
}
