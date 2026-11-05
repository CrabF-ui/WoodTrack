<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_manager_sees_inventory_but_no_admin_section_text_or_links(): void
    {
        $response = $this->actingAs(User::factory()->encargadoInventario()->create())->get('/dashboard');

        $response->assertOk()
            ->assertSee('Inventario')
            ->assertSee('href="'.route('inventario.index').'"', false)
            ->assertDontSee('Pedidos')
            ->assertDontSee('Clientes')
            ->assertDontSee('Entregas')
            ->assertDontSee(route('pedidos.index'))
            ->assertDontSee(route('clientes.index'))
            ->assertDontSee(route('entregas.index'));
    }

    public function test_admin_sees_links_to_pedidos_clientes_entregas_and_inventario(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk()
            ->assertSee('Pedidos')
            ->assertSee('Clientes')
            ->assertSee('Entregas')
            ->assertSee('Inventario')
            ->assertSee('href="'.route('pedidos.index').'"', false)
            ->assertSee('href="'.route('clientes.index').'"', false)
            ->assertSee('href="'.route('entregas.index').'"', false)
            ->assertSee('href="'.route('inventario.index').'"', false);
    }
}
