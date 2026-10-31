<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertaBajoStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function material(string $nombre, $actual, $minimo): Material
    {
        return Material::forceCreate([
            'nombre' => $nombre,
            'tipo' => 'madera',
            'unidad_medida' => 'pza',
            'stock_actual' => $actual,
            'stock_minimo' => $minimo,
        ]);
    }

    public function test_stock_igual_al_minimo_genera_alerta(): void
    {
        $this->material('Pino', 10, 10);

        $this->get(route('inventario.index'))->assertSee('1 material en bajo stock');
        $this->get(route('dashboard'))->assertSee('Ver materiales');
    }

    public function test_con_una_pieza_mas_no_genera_alerta(): void
    {
        $this->material('Pino', 11, 10);

        $this->get(route('inventario.index'))->assertDontSee('en bajo stock');
        $this->get(route('dashboard'))->assertDontSee('Ver materiales');
    }

    public function test_conteo_y_enlace_son_correctos(): void
    {
        $this->material('Pino', 5, 10);    // bajo
        $this->material('Cedro', 10, 10);  // igual -> bajo
        $this->material('Roble', 11, 10);  // ok

        $this->get(route('inventario.index'))
            ->assertSee('2 materiales en bajo stock')
            ->assertSee(route('inventario.index', ['bajo_stock' => 1]), false);

        $this->get(route('inventario.index', ['bajo_stock' => 1]))
            ->assertSee('Pino')->assertSee('Cedro')->assertDontSee('Roble');
    }
}