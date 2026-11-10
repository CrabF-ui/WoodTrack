<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoEspecificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_especificaciones_separadas_para_dos_muebles(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->pedidoValido([
            'detalles' => [
                $this->detalle('Clóset', 1, 100, '220 cm', '180 cm', '60 cm', 'Pino', 'Natural'),
                $this->detalle('Escritorio', 2, 75, '75 cm', '120 cm', '60 cm', 'Encino', 'Nogal'),
            ],
        ]);

        $this->post(route('pedidos.store'), $payload)->assertRedirect(route('pedidos.index'))->assertSessionHasNoErrors();

        $detalles = Pedido::firstOrFail()->detalles()->orderBy('id')->get();
        $this->assertSame('220 cm', $detalles[0]->alto);
        $this->assertSame('Natural', $detalles[0]->acabado_color);
        $this->assertSame('75 cm', $detalles[1]->alto);
        $this->assertSame('Nogal', $detalles[1]->acabado_color);
    }

    public function test_editar_un_mueble_conserva_las_especificaciones_del_otro(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->crearPedidoConDetalles([
            $this->detalle('Clóset', 1, 100, '220 cm', '180 cm', '60 cm', 'Pino', 'Natural'),
            $this->detalle('Escritorio', 1, 75, '75 cm', '120 cm', '60 cm', 'Encino', 'Nogal'),
        ]);
        $payload = $this->pedidoValido([
            'detalles' => [
                $this->detalle('Clóset alto', 1, 100, '240 cm', '180 cm', '60 cm', 'Pino', 'Natural'),
                $this->detalle('Escritorio', 1, 75, '75 cm', '120 cm', '60 cm', 'Encino', 'Nogal'),
            ],
        ]);

        $this->put(route('pedidos.update', $pedido), $payload)->assertRedirect(route('pedidos.index'))->assertSessionHasNoErrors();

        $detalles = $pedido->fresh()->detalles()->orderBy('id')->get();
        $this->assertSame('240 cm', $detalles[0]->alto);
        $this->assertSame('75 cm', $detalles[1]->alto);
        $this->assertSame('Encino', $detalles[1]->material_principal);
    }

    public function test_especificaciones_son_opcionales(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('pedidos.store'), $this->pedidoValido([
            'detalles' => [$this->detalle('Banco', 1, 50)],
        ]))->assertRedirect(route('pedidos.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('detalle_pedidos', [
            'producto' => 'Banco',
            'alto' => null,
            'acabado_color' => null,
        ]);
    }

    public function test_rechaza_especificacion_mayor_a_255_caracteres_con_mensaje_en_espanol(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('pedidos.create'))->post(route('pedidos.store'), $this->pedidoValido([
            'detalles' => [$this->detalle('Mesa', 1, 100, str_repeat('a', 256))],
        ]))->assertRedirect(route('pedidos.create'))
            ->assertSessionHasErrors(['detalles.0.alto' => 'El alto del mueble no puede superar los 255 caracteres.']);
    }

    public function test_vista_edicion_muestra_especificaciones_y_titulos_de_muebles(): void
    {
        $this->actingAs(User::factory()->create());
        $pedido = $this->crearPedidoConDetalles([
            $this->detalle('Clóset', 1, 100, '220 cm', '180 cm', '60 cm', 'Pino', 'Natural'),
            $this->detalle('Escritorio', 1, 75, '75 cm', '120 cm', '60 cm', 'Encino', 'Nogal'),
        ]);

        $this->get(route('pedidos.edit', $pedido))->assertOk()
            ->assertSee('Mueble 1 — Clóset')
            ->assertSee('Mueble 2 — Escritorio')
            ->assertSee('value="220 cm"', false)
            ->assertSee('value="75 cm"', false)
            ->assertSee('value="Encino"', false)
            ->assertSee('value="Nogal"', false);
    }

    public function test_vista_crear_incluye_especificaciones_por_mueble_y_quita_la_tarjeta_anterior(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pedidos.create'))->assertOk()
            ->assertSee('detalles[${nextDetalleIndex}][alto]', false)
            ->assertSee('detalles[${nextDetalleIndex}][ancho]', false)
            ->assertSee('detalles[${nextDetalleIndex}][profundidad]', false)
            ->assertSee('detalles[${nextDetalleIndex}][material_principal]', false)
            ->assertSee('detalles[${nextDetalleIndex}][acabado_color]', false)
            ->assertDontSee('Especificaciones técnicas y medidas');
    }

    public function test_calcula_el_total_de_los_detalles_e_ignora_el_total_enviado(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->pedidoValido([
            'detalles' => [$this->detalle('Mesa', 2, 100), $this->detalle('Banco', 1, 50)],
            'total' => 1,
        ]);

        $this->post(route('pedidos.store'), $payload)->assertRedirect(route('pedidos.index'))->assertSessionHasNoErrors();

        $this->assertSame('250.00', Pedido::firstOrFail()->total);
    }

    public function test_anticipo_se_valida_contra_el_total_calculado(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->pedidoValido([
            'detalles' => [$this->detalle('Mesa', 1, 100)],
            'total' => 10000,
            'forma_pago' => 'Anticipo',
            'monto_anticipo' => 101,
            'fecha_anticipo' => now()->toDateString(),
        ]);

        $this->from(route('pedidos.create'))->post(route('pedidos.store'), $payload)->assertRedirect(route('pedidos.create'))
            ->assertSessionHasErrors(['monto_anticipo' => 'El anticipo no puede ser mayor que el total.']);

        $payload['monto_anticipo'] = 100;
        $this->post(route('pedidos.store'), $payload)->assertRedirect(route('pedidos.index'))->assertSessionHasNoErrors();
        $this->assertSame('100.00', Pedido::firstOrFail()->total);
    }

    public function test_formulario_muestra_el_total_sin_enviar_un_input_total(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pedidos.create'))->assertOk()
            ->assertSee('id="total-calculado"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="total"', false);
    }

    public function test_formulario_muestra_saldo_pendiente_sin_enviar_un_input(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pedidos.create'))->assertOk()
            ->assertSee('id="saldo-pendiente"', false)
            ->assertDontSee('name="saldo-pendiente"', false);
    }

    private function crearPedidoConDetalles(array $detalles): Pedido
    {
        $pedido = app(\App\Repositories\Contracts\PedidoRepositoryInterface::class)->create(
            $this->pedidoValido(['detalles' => $detalles]),
        );

        return $pedido;
    }

    private function pedidoValido(array $overrides = []): array
    {
        $cliente = Cliente::create(['nombre' => 'Cliente WoodTrack', 'telefono' => '5550100']);

        return array_replace([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'direccion' => 'Calle de prueba 123',
            'detalles' => [$this->detalle('Mesa', 1, 100)],
            'herrajes' => [],
            'forma_pago' => 'Liquidado',
            'monto_anticipo' => null,
            'fecha_anticipo' => null,
        ], $overrides);
    }

    private function detalle(string $producto, int $cantidad, float $precio, ?string $alto = null, ?string $ancho = null, ?string $profundidad = null, ?string $material = null, ?string $acabado = null): array
    {
        return [
            'producto' => $producto,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'alto' => $alto,
            'ancho' => $ancho,
            'profundidad' => $profundidad,
            'material_principal' => $material,
            'acabado_color' => $acabado,
        ];
    }
}
