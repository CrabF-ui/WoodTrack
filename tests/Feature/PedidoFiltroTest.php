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

    private function createOrder(string $clientName): Pedido
    {
        $cliente = Cliente::create([
            'nombre' => $clientName,
            'telefono' => '555-0100',
        ]);

        return Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'total' => 1200,
            'direccion' => 'Dirección de prueba',
            'forma_pago' => 'Liquidado',
        ]);
    }
}
