<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PedidoHerrajesAlCompletarTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_completado_sin_herrajes_requiere_confirmacion(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('pedidos.store'), $this->payload(['estado' => 'completado']))
            ->assertSessionHasErrors([
                'confirmar_sin_herrajes' => 'Antes de completar el pedido, captura los herrajes utilizados o confirma que no lleva herrajes.',
            ]);
    }

    public function test_store_completado_con_herrajes_no_requiere_confirmacion(): void
    {
        $material = $this->material();

        $this->actingAs(User::factory()->create())
            ->post(route('pedidos.store'), $this->payload([
                'estado' => 'completado',
                'herrajes' => [['material_id' => $material->id, 'cantidad' => 2]],
            ]))
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pedido_herrajes', 1);
    }

    public function test_store_completado_sin_herrajes_acepta_confirmacion_y_no_la_guarda(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('pedidos.store'), $this->payload([
                'estado' => 'completado',
                'confirmar_sin_herrajes' => '1',
            ]))
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();

        $pedido = Pedido::firstOrFail();
        $this->assertFalse(Schema::hasColumn('pedidos', 'confirmar_sin_herrajes'));
        $this->assertArrayNotHasKey('confirmar_sin_herrajes', $pedido->getAttributes());
    }

    public function test_update_de_en_proceso_a_completado_sin_herrajes_requiere_confirmacion(): void
    {
        $pedido = $this->pedido('en_proceso');

        $this->actingAs(User::factory()->create())
            ->put(route('pedidos.update', $pedido), $this->payloadPedido($pedido, ['estado' => 'completado']))
            ->assertSessionHasErrors('confirmar_sin_herrajes');
    }

    public function test_update_de_en_proceso_a_completado_sin_herrajes_acepta_confirmacion(): void
    {
        $pedido = $this->pedido('en_proceso');

        $this->actingAs(User::factory()->create())
            ->put(route('pedidos.update', $pedido), $this->payloadPedido($pedido, [
                'estado' => 'completado',
                'confirmar_sin_herrajes' => '1',
            ]))
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('completado', $pedido->refresh()->estado);
    }

    public function test_update_a_completado_con_herrajes_guardados_no_requiere_confirmacion_si_no_llega_la_clave(): void
    {
        $pedido = $this->pedido('en_proceso');
        $pedido->herrajes()->create(['material_id' => $this->material()->id, 'cantidad' => 1]);

        $this->actingAs(User::factory()->create())
            ->put(route('pedidos.update', $pedido), $this->payloadPedido($pedido, ['estado' => 'completado']))
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_update_de_pedido_ya_completado_no_requiere_confirmacion(): void
    {
        $pedido = $this->pedido('completado');

        $this->actingAs(User::factory()->create())
            ->put(route('pedidos.update', $pedido), $this->payloadPedido($pedido, ['direccion' => 'Nueva dirección']))
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_estados_que_no_son_completado_no_requieren_confirmacion(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['pendiente', 'en_proceso', 'cancelado'] as $estado) {
            $this->post(route('pedidos.store'), $this->payload(['estado' => $estado]))
                ->assertSessionDoesntHaveErrors('confirmar_sin_herrajes');
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'cliente_id' => Cliente::create(['nombre' => 'Cliente'])->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'direccion' => 'Dirección de prueba',
            'detalles' => [['producto' => 'Mesa', 'cantidad' => 1, 'precio_unitario' => 100]],
            'forma_pago' => 'Liquidado',
        ], $overrides);
    }

    private function payloadPedido(Pedido $pedido, array $overrides = []): array
    {
        return array_replace([
            'cliente_id' => $pedido->cliente_id,
            'fecha_pedido' => $pedido->fecha_pedido->toDateString(),
            'fecha_entrega' => $pedido->fecha_entrega->toDateString(),
            'estado' => $pedido->estado,
            'direccion' => $pedido->direccion,
            'detalles' => [['producto' => 'Mesa', 'cantidad' => 1, 'precio_unitario' => 100]],
            'forma_pago' => 'Liquidado',
        ], $overrides);
    }

    private function pedido(string $estado): Pedido
    {
        return Pedido::create([
            'cliente_id' => Cliente::create(['nombre' => 'Cliente existente'])->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => $estado,
            'total' => 100,
            'direccion' => 'Dirección existente',
            'forma_pago' => 'Liquidado',
        ]);
    }

    private function material(): Material
    {
        return Material::create([
            'nombre' => 'Bisagra '.fake()->unique()->numerify('###'),
            'tipo' => 'herraje',
            'unidad_medida' => 'pieza',
            'stock_actual' => 10,
            'stock_minimo' => 0,
        ]);
    }
}
