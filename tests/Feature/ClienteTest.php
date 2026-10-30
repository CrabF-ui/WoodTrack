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

    public function test_crea_clientes_con_o_sin_telefono_y_valida_los_campos(): void
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

    public function test_actualiza_un_cliente(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Nombre anterior', 'telefono' => '111']);

        $this->put(route('clientes.update', $cliente), ['nombre' => 'Nombre actualizado', 'telefono' => '222'])
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Nombre actualizado', 'telefono' => '222']);
    }

    public function test_filtra_y_busca_clientes(): void
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

    public function test_da_de_baja_y_restaura_clientes_conservando_los_filtros(): void
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

    public function test_clientes_dados_de_baja_no_se_ofrecen_en_pedidos_y_se_conserva_el_nombre_historico(): void
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

    public function test_avisa_si_no_hay_clientes_activos_y_muestra_los_disponibles(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('pedidos.create'))->assertOk()->assertSee('No hay clientes registrados')->assertSee(route('clientes.create'));

        Cliente::create(['nombre' => 'Cliente disponible']);
        $this->get(route('pedidos.create'))->assertOk()->assertSee('Cliente disponible');
    }

    public function test_los_invitados_son_redirigidos_al_inicio_de_sesion(): void
    {
        $this->get(route('clientes.index'))->assertRedirect(route('login'));
    }

    public function test_rechaza_crear_un_cliente_con_nombre_que_ya_esta_activo(): void
    {
        $this->actingAs(User::factory()->create());
        Cliente::create(['nombre' => 'Mueblería Roble']);

        $this->from(route('clientes.create'))
            ->post(route('clientes.store'), ['nombre' => '  Mueblería Roble  '])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un cliente con ese nombre.']);

        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_edita_sin_cambiar_el_nombre_y_rechaza_el_nombre_de_otro_cliente_activo(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Mueblería Roble']);
        Cliente::create(['nombre' => 'Taller Cedro']);

        $this->put(route('clientes.update', $cliente), ['nombre' => '  Mueblería Roble  '])
            ->assertRedirect(route('clientes.index'));
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Mueblería Roble']);

        $this->from(route('clientes.edit', $cliente))
            ->put(route('clientes.update', $cliente), ['nombre' => 'Taller Cedro'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un cliente con ese nombre.']);
    }

    public function test_permite_reutilizar_el_nombre_de_un_cliente_dado_de_baja(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Mueblería Roble']);
        $cliente->delete();

        $this->post(route('clientes.store'), ['nombre' => 'Mueblería Roble'])
            ->assertRedirect(route('clientes.index'));

        $this->assertDatabaseCount('clientes', 2);
        $this->assertSame(1, Cliente::where('nombre', 'Mueblería Roble')->count());
    }

    public function test_bloquea_restaurar_si_el_nombre_ya_esta_en_uso_y_mantiene_la_baja(): void
    {
        $this->actingAs(User::factory()->create());
        $eliminado = Cliente::create(['nombre' => 'Mueblería Roble']);
        $eliminado->delete();
        Cliente::create(['nombre' => 'Mueblería Roble']);

        $this->post(route('clientes.restore', ['id' => $eliminado->id, 'estado' => 'eliminados']))
            ->assertRedirect(route('clientes.index', ['estado' => 'eliminados']))
            ->assertSessionHas('error', 'No se puede restaurar el cliente porque ya existe un cliente activo con ese nombre.');

        $this->assertSoftDeleted('clientes', ['id' => $eliminado->id]);
    }

    public function test_permite_repetir_el_telefono_entre_clientes(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('clientes.store'), ['nombre' => 'Mueblería Roble', 'telefono' => '555-0100'])->assertRedirect(route('clientes.index'));
        $this->post(route('clientes.store'), ['nombre' => 'Taller Cedro', 'telefono' => '555-0100'])->assertRedirect(route('clientes.index'));

        $this->assertDatabaseCount('clientes', 2);
        $this->assertSame(2, Cliente::where('telefono', '555-0100')->count());
    }
}
