<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_clients_with_or_without_phone_and_validates_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('clientes.store'), ['nombre' => 'Mueblería Roble', 'telefono' => '555-0100'])
            ->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['nombre' => 'Mueblería Roble', 'telefono' => '555-0100']);

        $this->post(route('clientes.store'), ['nombre' => 'Taller Cedro'])
            ->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['nombre' => 'Taller Cedro', 'telefono' => null]);

        $this->from(route('clientes.create'))->post(route('clientes.store'), [])->assertSessionHasErrors('nombre');
        $this->from(route('clientes.create'))->post(route('clientes.store'), ['nombre' => str_repeat('a', 121)])->assertSessionHasErrors('nombre');
        $this->from(route('clientes.create'))->post(route('clientes.store'), ['nombre' => 'Válido', 'telefono' => str_repeat('1', 31)])->assertSessionHasErrors('telefono');
    }

    public function test_it_updates_a_client(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Nombre anterior', 'telefono' => '111']);

        $this->put(route('clientes.update', $cliente), ['nombre' => 'Nombre actualizado', 'telefono' => '222'])
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Nombre actualizado', 'telefono' => '222']);
    }

    public function test_it_filters_and_searches_clients(): void
    {
        $this->actingAs(User::factory()->create());
        Cliente::create(['nombre' => 'Roble activo']);
        $deleted = Cliente::create(['nombre' => 'Roble eliminado']);
        $deleted->delete();
        Cliente::create(['nombre' => 'Cedro activo']);

        $this->get(route('clientes.index', ['estado' => 'activos', 'q' => 'Roble']))
            ->assertOk()->assertSee('Roble activo')->assertDontSee('Roble eliminado');
        $this->get(route('clientes.index', ['estado' => 'eliminados']))
            ->assertOk()->assertSee('Roble eliminado')->assertDontSee('Roble activo');
        $this->get(route('clientes.index', ['estado' => 'todos']))
            ->assertOk()->assertSee('Roble activo')->assertSee('Roble eliminado')->assertSee('Cedro activo');
    }

    public function test_it_soft_deletes_and_restores_clients_preserving_list_filters(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Cliente para baja']);

        $this->delete(route('clientes.destroy', ['cliente' => $cliente->id, 'estado' => 'activos', 'q' => 'Cliente']))
            ->assertRedirect(route('clientes.index', ['estado' => 'activos', 'q' => 'Cliente']))
            ->assertSessionHas('success');
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);

        $this->get(route('clientes.index'))->assertOk()->assertDontSee('Cliente para baja');
        $this->get(route('clientes.index', ['estado' => 'eliminados']))->assertOk()->assertSee('Cliente para baja');

        $this->post(route('clientes.restore', ['id' => $cliente->id, 'estado' => 'eliminados', 'q' => 'Cliente']))
            ->assertRedirect(route('clientes.index', ['estado' => 'eliminados', 'q' => 'Cliente']))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'deleted_at' => null]);
    }

    public function test_deleted_clients_are_not_available_for_new_orders_and_orders_keep_their_client_name(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Cliente conservado']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'total' => 100,
            'direccion' => 'Calle de prueba',
            'forma_pago' => 'Liquidado',
        ]);
        $cliente->delete();

        $this->get(route('pedidos.create'))->assertOk()->assertDontSee('Cliente conservado');
        $this->post(route('pedidos.store'), ['cliente_id' => $cliente->id])->assertSessionHasErrors('cliente_id');
        $this->get(route('pedidos.index'))->assertOk()->assertSee('Cliente conservado');
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'cliente_id' => $cliente->id, 'deleted_at' => null]);
    }

    public function test_order_creation_shows_active_client_or_registration_link_when_none_exist(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('pedidos.create'))->assertOk()->assertSee('No hay clientes registrados')->assertSee(route('clientes.create'));

        Cliente::create(['nombre' => 'Cliente disponible']);
        $this->get(route('pedidos.create'))->assertOk()->assertSee('Cliente disponible');
    }

    public function test_guests_are_redirected_to_login_from_client_pages(): void
    {
        $this->get(route('clientes.index'))->assertRedirect(route('login'));
    }
}
