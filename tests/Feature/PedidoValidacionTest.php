<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoValidacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_the_mandatory_order_fields(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pedidos.store'), [])
            ->assertSessionHasErrors([
                'cliente_id' => 'El campo cliente es obligatorio.',
                'fecha_pedido' => 'El campo fecha del pedido es obligatorio.',
                'fecha_entrega' => 'El campo fecha de entrega es obligatorio.',
                'estado' => 'El campo estado es obligatorio.',
                'direccion' => 'El campo dirección es obligatorio.',
                'detalles' => 'El campo productos del pedido es obligatorio.',
                'total' => 'El campo total es obligatorio.',
                'forma_pago' => 'El campo forma de pago es obligatorio.',
            ]);
    }

    public function test_it_rejects_an_delivery_date_before_the_order_date(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pedidos.store'), $this->validOrder([
            'fecha_pedido' => '2026-10-20',
            'fecha_entrega' => '2026-10-19',
        ]))->assertSessionHasErrors([
            'fecha_entrega' => 'La fecha de entrega no puede ser anterior a la fecha del pedido.',
        ]);
    }

    public function test_it_rejects_a_state_outside_the_order_catalog(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pedidos.store'), $this->validOrder(['estado' => 'en espera']))
            ->assertSessionHasErrors(['estado' => 'Selecciona un estado válido.']);
    }

    public function test_it_rejects_a_soft_deleted_client(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Cliente eliminado', 'telefono' => null]);
        $cliente->delete();

        $this->post(route('pedidos.store'), $this->validOrder(['cliente_id' => $cliente->id]))
            ->assertSessionHasErrors(['cliente_id' => 'Selecciona un cliente válido y activo.']);
    }

    public function test_it_rejects_duplicate_hardware_and_zero_quantity(): void
    {
        $this->actingAs(User::factory()->create());
        $material = Material::create([
            'nombre' => 'Bisagra',
            'tipo' => 'herraje',
            'unidad_medida' => 'pieza',
            'stock_actual' => 10,
            'stock_minimo' => 0,
        ]);

        $this->post(route('pedidos.store'), $this->validOrder([
            'herrajes' => [
                ['material_id' => $material->id, 'cantidad' => 1],
                ['material_id' => $material->id, 'cantidad' => 0],
            ],
        ]))->assertSessionHasErrors([
            'herrajes.1.material_id' => 'No repitas el mismo herraje.',
            'herrajes.1.cantidad' => 'La cantidad de herrajes debe ser mayor que cero.',
        ]);
    }

    public function test_it_rejects_an_advance_greater_than_the_order_total(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pedidos.store'), $this->validOrder([
            'forma_pago' => 'Anticipo',
            'monto_anticipo' => 101,
            'fecha_anticipo' => now()->toDateString(),
        ]))->assertSessionHasErrors([
            'monto_anticipo' => 'El anticipo no puede ser mayor que el total.',
        ]);
    }

    public function test_it_requires_advance_amount_and_date_for_advance_payments(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pedidos.store'), $this->validOrder([
            'forma_pago' => 'Anticipo',
            'monto_anticipo' => null,
            'fecha_anticipo' => null,
        ]))->assertSessionHasErrors([
            'monto_anticipo' => 'El campo monto del anticipo es obligatorio.',
            'fecha_anticipo' => 'El campo fecha del anticipo es obligatorio.',
        ]);
    }

    public function test_liquidated_payment_clears_advance_amount_and_date(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->validOrder([
            'forma_pago' => 'Liquidado',
            'monto_anticipo' => 'no es un monto',
            'fecha_anticipo' => 'fecha inválida',
        ]);

        $this->post(route('pedidos.store'), $payload)
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pedidos', [
            'cliente_id' => $payload['cliente_id'],
            'forma_pago' => 'Liquidado',
            'monto_anticipo' => null,
            'fecha_anticipo' => null,
        ]);
    }

    public function test_a_valid_order_is_created_successfully(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->validOrder();

        $this->post(route('pedidos.store'), $payload)
            ->assertRedirect(route('pedidos.index'))
            ->assertSessionHasNoErrors();

        $pedido = Pedido::with('detalles')->firstOrFail();
        $this->assertSame('pendiente', $pedido->estado);
        $this->assertSame('Escritorio', $pedido->detalles->first()->producto);
    }

    private function validOrder(array $overrides = []): array
    {
        $cliente = Cliente::create(['nombre' => 'Cliente válido', 'telefono' => '555-0100']);

        return array_replace([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'direccion' => 'Calle de prueba 123',
            'detalles' => [
                ['producto' => 'Escritorio', 'cantidad' => 1, 'precio_unitario' => 100],
            ],
            'herrajes' => [],
            'total' => 100,
            'forma_pago' => 'Liquidado',
            'monto_anticipo' => null,
            'fecha_anticipo' => null,
        ], $overrides);
    }
}
