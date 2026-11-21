<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaPedidoPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceso_a_nota_pdf_por_rol(): void
    {
        $pedido = $this->pedido();

        $response = $this->actingAs(User::factory()->create())->get(route('pedidos.nota', $pedido));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('pedidos.nota', $pedido))
            ->assertForbidden();
        $this->actingAs(User::factory()->chofer()->create())
            ->get(route('pedidos.nota', $pedido))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('pedidos.nota', $pedido))->assertRedirect(route('login'));
    }

    public function test_pedido_eliminado_e_inexistente_responden_404(): void
    {
        $pedido = $this->pedido();
        $pedido->delete();
        $this->actingAs(User::factory()->create());

        $this->get(route('pedidos.nota', $pedido->id))->assertNotFound();
        $this->get(route('pedidos.nota', 999999))->assertNotFound();
    }

    public function test_vista_liquidada_muestra_datos_productos_y_total_sin_saldo(): void
    {
        $pedido = $this->pedido([
            'fecha_pedido' => '2026-11-01',
            'fecha_entrega' => '2026-11-15',
            'total' => 1234,
            'direccion' => 'Avenida Cañón 123',
            'forma_pago' => 'Liquidado',
        ], ['nombre' => 'Niña Diseño', 'telefono' => '5551234567']);
        $pedido->detalles()->create([
            'producto' => 'Armario de año',
            'cantidad' => 2,
            'precio_unitario' => 617,
            'alto' => '200 cm',
            'ancho' => '120 cm',
            'profundidad' => '60 cm',
            'material_principal' => 'Madera de pino',
            'acabado_color' => 'Café cañón',
        ]);
        $html = $this->renderizar($pedido);

        foreach (['#'.$pedido->id, '01/11/2026', '15/11/2026', 'Niña Diseño', '5551234567', 'Avenida Cañón 123', 'Armario de año', '200 cm × 120 cm × 60 cm', 'Madera de pino', 'Café cañón', '$617.00', '$1,234.00'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringContainsString('<td>2</td>', $html);
        $this->assertStringNotContainsString('Saldo', $html);
    }

    public function test_vista_con_anticipo_muestra_monto_fecha_y_saldo(): void
    {
        $pedido = $this->pedido([
            'total' => 1500,
            'forma_pago' => 'Anticipo',
            'monto_anticipo' => 400,
            'fecha_anticipo' => '2026-11-05',
        ]);
        $pedido->detalles()->create(['producto' => 'Mesa', 'cantidad' => 1, 'precio_unitario' => 1500]);

        $html = $this->renderizar($pedido);

        $this->assertStringContainsString('Monto del anticipo', $html);
        $this->assertStringContainsString('$400.00', $html);
        $this->assertStringContainsString('05/11/2026', $html);
        $this->assertStringContainsString('Saldo', $html);
        $this->assertStringContainsString('$1,100.00', $html);
    }

    public function test_cancelado_muestra_recuadro_y_pedido_vigente_no(): void
    {
        $cancelado = $this->pedido(['estado' => 'cancelado']);
        $vigente = $this->pedido(['estado' => 'pendiente']);

        $this->assertStringContainsString('<div class="cancelado">CANCELADO</div>', $this->renderizar($cancelado));
        $this->assertStringNotContainsString('CANCELADO', $this->renderizar($vigente));
    }

    public function test_sin_telefono_muestra_guion(): void
    {
        $pedido = $this->pedido([], ['nombre' => 'Cliente sin teléfono', 'telefono' => null]);
        $html = $this->renderizar($pedido);

        $this->assertStringContainsString('<span class="label">Teléfono</span>—', $html);
    }

    public function test_nota_no_incluye_herrajes_aunque_el_pedido_los_tenga(): void
    {
        $pedido = $this->pedido();
        $material = Material::create([
            'nombre' => 'Bisagra interna reforzada',
            'tipo' => 'herraje',
            'unidad_medida' => 'pieza',
            'stock_actual' => 10,
            'stock_minimo' => 1,
        ]);
        $pedido->herrajes()->create(['material_id' => $material->id, 'cantidad' => 4]);

        $html = $this->renderizar($pedido);

        $this->assertStringNotContainsString('Herrajes', $html);
        $this->assertStringNotContainsString('Bisagra interna reforzada', $html);
    }

    public function test_listado_muestra_nota_pdf_solo_para_pedidos_no_eliminados(): void
    {
        $activo = $this->pedido();
        $eliminado = $this->pedido();
        $eliminado->delete();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('pedidos.index', ['estado' => 'todos']))
            ->assertOk();

        $response->assertSee('href="'.route('pedidos.nota', $activo).'"', false);
        $response->assertDontSee('href="'.route('pedidos.nota', $eliminado->id).'"', false);
    }

    private function renderizar(Pedido $pedido): string
    {
        $this->actingAs(User::factory()->create());

        return view('reportes.nota-pedido', [
            'pedido' => $pedido->load(['cliente', 'detalles']),
        ])->render();
    }

    private function pedido(array $pedido = [], array $cliente = []): Pedido
    {
        $cliente = Cliente::create(array_replace([
            'nombre' => 'Cliente de prueba',
            'telefono' => '5550000000',
        ], $cliente));

        return Pedido::create(array_replace([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => '2026-11-01',
            'fecha_entrega' => '2026-11-15',
            'estado' => 'pendiente',
            'total' => 100,
            'direccion' => 'Calle de prueba',
            'forma_pago' => 'Liquidado',
        ], $pedido));
    }
}
