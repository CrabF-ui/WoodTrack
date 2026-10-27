<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioMovimientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_entradas_y_salidas_con_el_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $material = $this->createMaterial(stock: 10);

        $this->post(route('inventario.movimientos.store'), [
            'material_id' => $material->id, 'tipo' => 'entrada', 'cantidad' => 5,
        ])->assertRedirect(route('inventario.movimientos.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 15]);
        $this->assertDatabaseHas('movimiento_inventarios', [
            'material_id' => $material->id, 'tipo' => 'entrada', 'cantidad' => 5, 'registrado_por' => $user->id,
        ]);

        $this->post(route('inventario.movimientos.store'), [
            'material_id' => $material->id, 'tipo' => 'salida', 'cantidad' => 3,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 12]);
    }

    public function test_rechaza_una_salida_mayor_al_stock_sin_crear_movimiento(): void
    {
        $this->actingAs(User::factory()->create());
        $material = $this->createMaterial(stock: 4);

        $this->from(route('inventario.movimientos.create'))
            ->post(route('inventario.movimientos.store'), [
                'material_id' => $material->id, 'tipo' => 'salida', 'cantidad' => 7,
            ])
            ->assertRedirect(route('inventario.movimientos.create'))
            ->assertSessionHas('error', "Stock insuficiente de '{$material->nombre}': disponible 4, solicitado 7.");

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 4]);
        $this->assertDatabaseCount('movimiento_inventarios', 0);
    }

    public function test_valida_cantidad_material_tipo_y_fecha_futura(): void
    {
        $this->actingAs(User::factory()->create());
        $material = $this->createMaterial();
        $deleted = $this->createMaterial('Material inactivo');
        $deleted->delete();

        foreach ([0, -1] as $quantity) {
            $this->from(route('inventario.movimientos.create'))
                ->post(route('inventario.movimientos.store'), [
                    'material_id' => $material->id, 'tipo' => 'entrada', 'cantidad' => $quantity,
                ])->assertSessionHasErrors('cantidad');
        }

        foreach ([999999999, 'no-existe', $deleted->id] as $materialId) {
            $this->from(route('inventario.movimientos.create'))
                ->post(route('inventario.movimientos.store'), [
                    'material_id' => $materialId, 'tipo' => 'entrada', 'cantidad' => 1,
                ])->assertSessionHasErrors('material_id');
        }

        $this->from(route('inventario.movimientos.create'))
            ->post(route('inventario.movimientos.store'), [
                'material_id' => $material->id, 'tipo' => 'invalid', 'cantidad' => 1,
            ])->assertSessionHasErrors('tipo');

        $this->from(route('inventario.movimientos.create'))
            ->post(route('inventario.movimientos.store'), [
                'material_id' => $material->id, 'tipo' => 'entrada', 'cantidad' => 1,
                'fecha' => now()->addDay()->toDateString(),
            ])->assertSessionHasErrors('fecha');
    }

    public function test_filtra_el_historial_por_tipo_material_origen_y_estado_de_baja(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->createMaterial('Roble');
        $second = $this->createMaterial('Tornillos', 'herraje');
        $entrada = $this->createMovement($first, 'entrada');
        $salida = $this->createMovement($first, 'salida');
        $pedido = $this->createMovement($second, 'salida', $this->createOrder()->id);
        $anulado = $this->createMovement($second, 'entrada');
        $anulado->delete();

        $this->get(route('inventario.movimientos.index', ['tipo' => 'entrada', 'material_id' => $first->id, 'origen' => 'manual']))
            ->assertOk()->assertSee('<td>Roble</td>', false)->assertSee('>Entrada</span>', false)
            ->assertDontSee('<td>Tornillos</td>', false)->assertDontSee('>Salida</span>', false);
        $this->get(route('inventario.movimientos.index', ['origen' => 'pedido']))
            ->assertOk()->assertSee("Automático (pedido #{$pedido->pedido_id})")
            ->assertDontSee('<td>Roble</td>', false);
        $this->get(route('inventario.movimientos.index', ['estado' => 'anulados']))
            ->assertOk()->assertSee('Anulado')->assertDontSee('<td>Roble</td>', false);
        $this->get(route('inventario.movimientos.index', ['q' => 'Roble']))
            ->assertOk()->assertSee('<td>Roble</td>', false)->assertDontSee('<td>Tornillos</td>', false);
    }

    public function test_anula_y_restaura_movimientos_manuales_y_bloquea_stock_negativo(): void
    {
        $this->actingAs(User::factory()->create());
        $material = $this->createMaterial(stock: 10);
        $movement = $this->createMovement($material, 'salida', quantity: 3);
        $material->decrement('stock_actual', 3);

        $this->delete(route('inventario.movimientos.destroy', $movement->id))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 10]);
        $this->get(route('inventario.movimientos.index'))->assertDontSee("#{$movement->id}");
        $this->post(route('inventario.movimientos.restore', $movement->id))->assertSessionHas('success');
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 7]);

        $entry = $this->createMovement($material, 'entrada', quantity: 5);
        $material->update(['stock_actual' => 4]);
        $this->delete(route('inventario.movimientos.destroy', $entry->id))
            ->assertSessionHas('error', "Stock insuficiente de '{$material->nombre}': disponible 4, solicitado 5.");
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 4]);

        $lowStock = $this->createMaterial('Poco stock', stock: 1);
        $largeExit = $this->createMovement($lowStock, 'salida', quantity: 2);
        $largeExit->delete();
        $this->post(route('inventario.movimientos.restore', $largeExit->id))->assertSessionHas('error');
        $this->assertDatabaseHas('materials', ['id' => $lowStock->id, 'stock_actual' => 1]);
    }

    public function test_bloquea_anular_y_restaurar_movimientos_de_pedido_incluso_por_ruta_directa(): void
    {
        $this->actingAs(User::factory()->create());
        $material = $this->createMaterial('Herraje', 'herraje', 5);
        $movement = $this->createMovement($material, 'salida', $this->createOrder()->id, 1);

        $this->delete(route('inventario.movimientos.destroy', $movement->id))->assertSessionHas('error');
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 5]);
        $this->assertDatabaseHas('movimiento_inventarios', ['id' => $movement->id, 'deleted_at' => null]);

        $movement->delete();
        $this->post(route('inventario.movimientos.restore', $movement->id))->assertSessionHas('error');
        $this->assertDatabaseHas('materials', ['id' => $material->id, 'stock_actual' => 5]);
    }

    public function test_usuario_no_autenticado_es_redirigido_al_inicio_de_sesion(): void
    {
        $this->get(route('inventario.index'))->assertRedirect(route('login'));
    }

    public function test_guardar_pedido_de_herrajes_no_cambia_stock_de_madera_ni_acabados(): void
    {
        $this->actingAs(User::factory()->create());
        $cliente = Cliente::create(['nombre' => 'Cliente madera', 'telefono' => '555-0100']);
        $herraje = $this->createMaterial('Tornillo', 'herraje', 20);
        $madera = $this->createMaterial('Tablero', 'madera', 12);
        $acabado = $this->createMaterial('Barniz', 'acabado', 8);

        $this->post(route('pedidos.store'), [
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'direccion' => 'Taller',
            'detalles' => [['producto' => 'Mesa', 'cantidad' => 1, 'precio_unitario' => 100]],
            'herrajes' => [['material_id' => $herraje->id, 'cantidad' => 2]],
            'total' => 100,
            'forma_pago' => 'Liquidado',
        ])->assertRedirect(route('pedidos.index'));

        $this->assertDatabaseHas('materials', ['id' => $herraje->id, 'stock_actual' => 18]);
        $this->assertDatabaseHas('materials', ['id' => $madera->id, 'stock_actual' => 12]);
        $this->assertDatabaseHas('materials', ['id' => $acabado->id, 'stock_actual' => 8]);
    }

    private function createMaterial(string $name = 'Madera de prueba', string $type = 'madera', float $stock = 0): Material
    {
        return Material::create([
            'nombre' => $name, 'tipo' => $type, 'unidad_medida' => 'pieza',
            'stock_actual' => $stock, 'stock_minimo' => 2,
        ]);
    }

    private function createMovement(Material $material, string $type, ?int $orderId = null, float $quantity = 1): MovimientoInventario
    {
        return MovimientoInventario::create([
            'material_id' => $material->id, 'tipo' => $type, 'cantidad' => $quantity,
            'motivo' => 'Prueba', 'registrado_por' => auth()->id(), 'pedido_id' => $orderId,
            'fecha' => now()->toDateString(),
        ]);
    }

    private function createOrder(): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'telefono' => '555-0100']);

        return Pedido::create([
            'cliente_id' => $cliente->id, 'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(), 'estado' => 'pendiente',
            'total' => 100, 'direccion' => 'Dirección de prueba', 'forma_pago' => 'Liquidado',
        ]);
    }
}
