<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaComprasPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceso_al_pdf_por_rol(): void
    {
        foreach ([User::factory()->create(), User::factory()->encargadoInventario()->create()] as $user) {
            $response = $this->actingAs($user)->get(route('reportes.lista-compras'));

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'inline; filename=lista-compras.pdf');
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }

        $this->actingAs(User::factory()->chofer()->create())
            ->get(route('reportes.lista-compras'))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('reportes.lista-compras'))->assertRedirect(route('login'));
    }

    public function test_vista_incluye_solo_materiales_activos_bajo_stock_con_faltantes_y_acentos(): void
    {
        $debajo = $this->material('Pino cañón', 'madera', 4, 10, 'm²');
        $igual = $this->material('Bisagra ñandú', 'herraje', 12, 12, 'pieza');
        $this->material('Barniz suficiente', 'acabado', 11, 10, 'litro');
        $eliminado = $this->material('Madera eliminada', 'madera', 1, 10, 'pieza');
        $eliminado->delete();

        $html = $this->renderizar();

        $this->assertStringContainsString('Total de materiales por comprar: 2', $html);
        $this->assertStringContainsString($debajo->nombre, $html);
        $this->assertStringContainsString($igual->nombre, $html);
        $this->assertStringContainsString('m²', $html);
        $this->assertStringNotContainsString('Barniz suficiente', $html);
        $this->assertStringNotContainsString('Madera eliminada', $html);
        $this->assertMatchesRegularExpression('/Pino cañón.*?4\.00.*?10\.00.*?6\.00/s', $html);
        $this->assertMatchesRegularExpression('/Bisagra ñandú.*?12\.00.*?12\.00.*?0\.00/s', $html);
        $this->assertStringContainsString('<td class="write-in">&nbsp;</td>', $html);
        $this->assertStringContainsString('Cantidad a comprar', $html);
    }

    public function test_vista_agrupa_en_orden_y_omite_tipos_sin_materiales(): void
    {
        $this->material('Zeta madera', 'madera', 1, 2);
        $this->material('Alfa madera', 'madera', 1, 2);
        $this->material('Bisagra', 'herraje', 1, 2);

        $html = $this->renderizar();

        $this->assertStringContainsString('Maderas', $html);
        $this->assertStringContainsString('Herrajes', $html);
        $this->assertStringNotContainsString('Acabados', $html);
        $this->assertLessThan(strpos($html, 'Herrajes'), strpos($html, 'Maderas'));
        $this->assertLessThan(strpos($html, 'Zeta madera'), strpos($html, 'Alfa madera'));
    }

    public function test_vista_sin_materiales_muestra_mensaje_en_lugar_de_tablas(): void
    {
        $html = $this->renderizar();

        $this->assertStringContainsString('Total de materiales por comprar: 0', $html);
        $this->assertStringContainsString('No hay materiales por debajo del stock mínimo.', $html);
        $this->assertStringNotContainsString('<table class="report-table">', $html);
    }

    public function test_botones_estan_disponibles_para_admin_y_encargado(): void
    {
        foreach ([User::factory()->create(), User::factory()->encargadoInventario()->create()] as $user) {
            $this->actingAs($user)->get(route('reportes.index'))
                ->assertOk()
                ->assertSee('Lista de compras')
                ->assertSee('href="'.route('reportes.lista-compras').'"', false)
                ->assertDontSee('Aún no hay reportes disponibles.');

            $this->actingAs($user)->get(route('inventario.materiales.index'))
                ->assertOk()
                ->assertSee('Lista de compras PDF')
                ->assertSee('href="'.route('reportes.lista-compras').'"', false)
                ->assertSee('target="_blank" rel="noopener"', false);
        }
    }

    private function renderizar(): string
    {
        $this->actingAs(User::factory()->create());

        return view('reportes.lista-compras', [
            'materiales' => app(MaterialRepositoryInterface::class)->getBajoStock(),
        ])->render();
    }

    private function material(
        string $nombre,
        string $tipo,
        float $stockActual,
        float $stockMinimo,
        string $unidad = 'pieza',
    ): Material {
        return Material::create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'unidad_medida' => $unidad,
            'stock_actual' => $stockActual,
            'stock_minimo' => $stockMinimo,
        ]);
    }
}
