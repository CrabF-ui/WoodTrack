<?php

namespace Tests\Unit;

use App\Services\GraficaBarras;
use Tests\TestCase;

class GraficaBarrasTest extends TestCase
{
    public function test_genera_una_data_uri_svg_valida(): void
    {
        $uri = app(GraficaBarras::class)->generar('Productos', ['Mesa', 'Silla'], [5, 2]);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = $this->decodificar($uri);
        $this->assertStringStartsWith('<svg ', $svg);
        $this->assertStringContainsString('<rect ', $svg);
        $this->assertStringEndsWith('</svg>', $svg);
    }

    public function test_escapa_textos_dinamicos_para_xml(): void
    {
        $uri = app(GraficaBarras::class)->generar('Título <ventas> & "mes"', ['Mesa <A> & "B"'], [3]);
        $svg = $this->decodificar($uri);

        $this->assertStringContainsString('Título &lt;ventas&gt; &amp; &quot;mes&quot;', $svg);
        $this->assertStringContainsString('Mesa &lt;A&gt; &amp; &quot;B&quot;', $svg);
        $this->assertStringNotContainsString('<A>', $svg);
    }

    public function test_trunca_etiquetas_largas_con_puntos_suspensivos(): void
    {
        $etiqueta = 'Producto artesanal extraordinariamente largo de madera';
        $svg = $this->decodificar(app(GraficaBarras::class)->generar('Productos', [$etiqueta], [1]));

        $this->assertStringContainsString('Producto artesanal extraord…', $svg);
        $this->assertStringNotContainsString($etiqueta, $svg);
    }

    public function test_no_genera_grafica_sin_datos_positivos(): void
    {
        $graficas = app(GraficaBarras::class);

        $this->assertNull($graficas->generar('Vacía', [], []));
        $this->assertNull($graficas->generar('Ceros', ['Uno', 'Dos'], [0, 0]));
    }

    private function decodificar(string $uri): string
    {
        return (string) base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
    }
}
