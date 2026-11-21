<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReportePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceso_al_indice_por_rol(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Lista de compras')
            ->assertSee('Nota de pedido')
            ->assertSee('Comprobante de entrega')
            ->assertSee('Problemas con entregas')
            ->assertDontSee('Aún no hay reportes disponibles.')
            ->assertSee('Probar el diseño base del PDF');
        $this->actingAs(User::factory()->encargadoInventario()->create())->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Lista de compras')
            ->assertDontSee('Aún no hay reportes disponibles.')
            ->assertDontSee('Nota de pedido')
            ->assertDontSee('Comprobante de entrega')
            ->assertDontSee('Problemas con entregas')
            ->assertDontSee('Probar el diseño base del PDF');
        $this->actingAs(User::factory()->chofer()->create())->get(route('reportes.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('reportes.index'))->assertRedirect(route('login'));
    }

    public function test_pdf_de_prueba_solo_es_accesible_para_admin(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('reportes.prueba'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=prueba-diseno-base.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('reportes.prueba'))
            ->assertForbidden();
        $this->actingAs(User::factory()->chofer()->create())
            ->get(route('reportes.prueba'))
            ->assertForbidden();
    }

    public function test_vista_de_prueba_renderiza_encabezado_usuario_fecha_y_acentos(): void
    {
        Carbon::setTestNow('2026-11-20 14:35:00');
        $user = User::factory()->create(['name' => 'Administradora Ñandú']);
        $this->actingAs($user);

        $html = view('reportes.prueba')->render();

        $this->assertStringContainsString('WoodTrack', $html);
        $this->assertStringContainsString('Administradora Ñandú', $html);
        $this->assertStringContainsString('20/11/2026 14:35', $html);
        $this->assertStringContainsString('Año, diseño, cañón, niño, camión', $html);
        $this->assertStringNotContainsString('text/php', $html);
    }

    public function test_servicio_genera_pdf_de_varias_paginas(): void
    {
        $this->actingAs(User::factory()->create());
        View::addNamespace('test-reportes', base_path('tests/Fixtures/views'));

        $response = app(ReportePdf::class)->generar('test-reportes::reporte-largo', [], 'reporte-largo.pdf');

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_sidebar_muestra_reportes_a_admin_y_encargado(): void
    {
        foreach ([User::factory()->create(), User::factory()->encargadoInventario()->create()] as $user) {
            $this->actingAs($user)->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Reportes')
                ->assertSee('href="'.route('reportes.index').'"', false);
        }
    }
}
