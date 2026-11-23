<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use App\Services\GraficaBarras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VentasMesPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceso_al_pdf_por_rol(): void
    {
        $this->pedido('2026-11-10', 450, 'Cliente PDF', 'Mesa PDF', 3, 150);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('reportes.ventas-mes', ['mes' => '2026-11']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=ventas-mes.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        foreach ([User::factory()->encargadoInventario()->create(), User::factory()->chofer()->create()] as $user) {
            $this->actingAs($user)->get(route('reportes.ventas-mes'))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('reportes.ventas-mes'))->assertRedirect(route('login'));
    }

    public function test_valida_formato_y_rechaza_meses_futuros(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20', config('reportes.zona_horaria')));
        $this->actingAs(User::factory()->create());

        try {
            $this->get(route('reportes.ventas-mes', ['mes' => 'noviembre']))
                ->assertRedirect(route('reportes.index'))
                ->assertSessionHasErrors('mes');
            $this->get(route('reportes.ventas-mes', ['mes' => '2026-12']))
                ->assertRedirect(route('reportes.index'))
                ->assertSessionHasErrors('mes');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sin_parametro_usa_el_mes_en_curso_de_la_zona_de_reportes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-30 23:30:00', config('reportes.zona_horaria')));
        $this->mock(PedidoRepositoryInterface::class, function ($mock): void {
            $mock->shouldReceive('getVentasMes')
                ->once()
                ->withArgs(fn ($mes): bool => $mes->format('Y-m') === '2026-11')
                ->andReturn($this->ventasVacias());
        });

        try {
            $response = $this->actingAs(User::factory()->create())->get(route('reportes.ventas-mes'));
            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_datos_incluyen_limites_y_excluyen_cancelados_eliminados_y_otros_meses(): void
    {
        $primero = $this->pedido('2026-11-01', 100, 'Cliente Primero', 'Mesa', 1, 100);
        $ultimo = $this->pedido('2026-11-30', 300, 'Cliente Último', 'Silla', 3, 100);
        $this->pedido('2026-10-31', 900, 'Mes Anterior', 'Fuera anterior', 1, 900);
        $this->pedido('2026-12-01', 800, 'Mes Siguiente', 'Fuera siguiente', 1, 800);
        $this->pedido('2026-11-15', 700, 'Cancelado', 'Fuera cancelado', 1, 700, 'cancelado');
        $eliminado = $this->pedido('2026-11-16', 600, 'Eliminado', 'Fuera eliminado', 1, 600);
        $eliminado->delete();

        $ventas = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'));

        $this->assertSame(400.0, $ventas['total']);
        $this->assertSame(2, $ventas['pedidos']);
        $this->assertSame(200.0, $ventas['ticketPromedio']);
        $this->assertSame([$ultimo->cliente->nombre, $primero->cliente->nombre], $ventas['topClientes']->pluck('nombre')->all());
        $this->assertSame(['Silla', 'Mesa'], $ventas['topProductos']->pluck('nombre')->all());
    }

    public function test_top_clientes_esta_ordenado_y_limitado_a_cinco(): void
    {
        foreach ([
            ['Cliente Zeta', 100],
            ['Cliente Beta', 500],
            ['Cliente Alfa', 500],
            ['Cliente Tres', 400],
            ['Cliente Cuatro', 300],
            ['Cliente Cinco', 200],
        ] as [$cliente, $total]) {
            $this->pedido('2026-11-10', $total, $cliente, 'Producto '.$cliente, 1, $total);
        }

        $top = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'))['topClientes'];

        $this->assertCount(5, $top);
        $this->assertSame(['Cliente Alfa', 'Cliente Beta', 'Cliente Tres', 'Cliente Cuatro', 'Cliente Cinco'], $top->pluck('nombre')->all());
    }

    public function test_top_productos_normaliza_nombres_ordena_y_limita_a_cinco(): void
    {
        $pedido = $this->pedido('2026-11-10', 1000, 'Cliente Productos', 'Mesa  Roble', 2, 100);
        $pedido->detalles()->create(['producto' => ' mesa roble ', 'cantidad' => 3, 'precio_unitario' => 120]);
        foreach ([
            ['Banco', 8],
            ['Armario', 8],
            ['Cómoda Ñandú', 6],
            ['Silla', 5],
            ['Buró', 4],
        ] as [$producto, $cantidad]) {
            $pedido->detalles()->create(['producto' => $producto, 'cantidad' => $cantidad, 'precio_unitario' => 10]);
        }

        $top = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'))['topProductos'];

        $this->assertCount(5, $top);
        $this->assertSame(['Armario', 'Banco', 'Cómoda Ñandú', 'Mesa Roble', 'Silla'], $top->pluck('nombre')->all());
        $mesa = $top->firstWhere('nombre', 'Mesa Roble');
        $this->assertSame(5.0, $mesa['cantidad']);
        $this->assertSame(560.0, $mesa['monto']);
    }

    public function test_comparacion_con_mes_anterior_calcula_diferencia_y_variacion(): void
    {
        $this->pedido('2026-10-10', 200, 'Cliente Octubre', 'Mesa', 1, 200);
        $this->pedido('2026-10-20', 300, 'Cliente Octubre Dos', 'Silla', 1, 300);
        $this->pedido('2026-11-10', 750, 'Cliente Noviembre', 'Armario', 1, 750);

        $comparacion = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'))['comparacion'];

        $this->assertSame(500.0, $comparacion['totalAnterior']);
        $this->assertSame(2, $comparacion['pedidosAnterior']);
        $this->assertSame(250.0, $comparacion['diferencia']);
        $this->assertSame(50.0, $comparacion['variacion']);
    }

    public function test_vista_muestra_el_signo_antes_del_simbolo_en_diferencias_negativas(): void
    {
        $this->pedido('2026-10-10', 300, 'Cliente Octubre', 'Mesa', 1, 300);
        $this->pedido('2026-11-10', 100, 'Cliente Noviembre', 'Silla', 1, 100);
        $ventas = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'));

        $html = $this->renderizar($ventas, '2026-11-01');

        $this->assertStringContainsString('-$200.00', $html);
        $this->assertStringNotContainsString('$-200.00', $html);
    }

    public function test_vista_muestra_cantidades_enteras_sin_decimales_y_fraccionarias_con_hasta_dos(): void
    {
        $ventas = $this->ventasVacias();
        $ventas['pedidos'] = 1;
        $ventas['topProductos'] = collect([
            ['nombre' => 'Mesa', 'cantidad' => 5.0, 'monto' => 500.0],
            ['nombre' => 'Silla', 'cantidad' => 2.5, 'monto' => 250.0],
        ]);

        $html = $this->renderizar($ventas, '2026-11-01');

        $this->assertMatchesRegularExpression('/Mesa<\/td>\s*<td>5<\/td>/', $html);
        $this->assertMatchesRegularExpression('/Silla<\/td>\s*<td>2\.5<\/td>/', $html);
        $this->assertStringNotContainsString('<td>5.00</td>', $html);
        $this->assertStringNotContainsString('<td>2.50</td>', $html);
    }

    public function test_comparacion_sin_ventas_previas_no_divide_entre_cero(): void
    {
        $this->pedido('2026-11-10', 250, 'Cliente Nuevo', 'Mesa', 1, 250);

        $ventas = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'));
        $html = $this->renderizar($ventas, '2026-11-01');

        $this->assertSame(0.0, $ventas['comparacion']['totalAnterior']);
        $this->assertSame(0, $ventas['comparacion']['pedidosAnterior']);
        $this->assertNull($ventas['comparacion']['variacion']);
        $this->assertStringContainsString('N/D (sin ventas el mes anterior)', $html);
    }

    public function test_vista_muestra_mes_en_espanol_acentos_y_definicion_de_venta(): void
    {
        $this->pedido('2026-11-10', 1234, 'Niña Cañón', 'Cómoda Ñandú', 2, 617);
        $ventas = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'));
        $html = $this->renderizar($ventas, '2026-11-01');

        foreach (['Mes: Noviembre 2026', '$1,234.00', 'Niña Cañón', 'Cómoda Ñandú', 'Definición de venta', 'pedidos no cancelados y no eliminados', 'no refleja cobros'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);
        $this->assertStringContainsString('Top 5 productos por cantidad vendida', $html);
        $this->assertStringContainsString('Ventas: mes anterior contra mes actual', $html);
    }

    public function test_mes_sin_ventas_muestra_mensaje_y_resumen_en_ceros(): void
    {
        $ventas = app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-11-01'));
        $html = $this->renderizar($ventas, '2026-11-01');

        $this->assertSame(0.0, $ventas['total']);
        $this->assertSame(0, $ventas['pedidos']);
        $this->assertSame(0.0, $ventas['ticketPromedio']);
        $this->assertStringContainsString('No hay ventas registradas en el mes seleccionado.', $html);
        $this->assertSame(2, substr_count($html, 'Sin datos para graficar en el periodo'));
        $this->assertStringNotContainsString('Top 5 clientes', $html);
        $this->assertStringNotContainsString('Top 5 productos', $html);
    }

    public function test_formulario_solo_se_muestra_a_admin(): void
    {
        $admin = $this->actingAs(User::factory()->create())->get(route('reportes.index'))->assertOk();
        $admin->assertSee('Generar Ventas del mes PDF')
            ->assertSee('action="'.route('reportes.ventas-mes').'"', false)
            ->assertSee('type="month"', false)
            ->assertSee('target="_blank"', false);

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertDontSee('Generar Ventas del mes PDF')
            ->assertDontSee('action="'.route('reportes.ventas-mes').'"', false);
    }

    private function pedido(
        string $fecha,
        float $total,
        string $cliente,
        string $producto,
        int $cantidad,
        float $precio,
        string $estado = 'pendiente',
    ): Pedido {
        $clienteModel = Cliente::create(['nombre' => $cliente]);
        $pedido = Pedido::create([
            'cliente_id' => $clienteModel->id,
            'fecha_pedido' => $fecha,
            'direccion' => 'Calle de venta',
            'estado' => $estado,
            'total' => $total,
        ]);
        $pedido->detalles()->create([
            'producto' => $producto,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
        ]);

        return $pedido->load('cliente');
    }

    private function renderizar(array $ventas, string $mes): string
    {
        $this->actingAs(User::factory()->create());
        $graficas = app(GraficaBarras::class);
        $fechaMes = Carbon::parse($mes);
        $graficaProductos = $graficas->generar(
            'Top 5 productos por cantidad vendida',
            $ventas['topProductos']->pluck('nombre')->all(),
            $ventas['topProductos']->pluck('cantidad')->all(),
        );
        $graficaComparacion = $graficas->generar(
            'Ventas: mes anterior contra mes actual',
            ['Mes anterior', 'Mes actual'],
            [$ventas['comparacion']['totalAnterior'], $ventas['total']],
            GraficaBarras::FORMATO_PESOS,
            GraficaBarras::ORIENTACION_VERTICAL,
        );

        return view('reportes.ventas-mes', [
            'ventas' => $ventas,
            'mes' => $fechaMes,
            'graficaProductos' => $graficaProductos,
            'graficaComparacion' => $graficaComparacion,
        ])->render();
    }

    private function ventasVacias(): array
    {
        return [
            'total' => 0.0,
            'pedidos' => 0,
            'ticketPromedio' => 0.0,
            'topClientes' => collect(),
            'topProductos' => collect(),
            'comparacion' => [
                'totalAnterior' => 0.0,
                'pedidosAnterior' => 0,
                'diferencia' => 0.0,
                'variacion' => null,
            ],
        ];
    }
}
