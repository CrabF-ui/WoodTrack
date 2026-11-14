<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Material;
use App\Models\MovimientoInventario;
use App\Models\Pedido;
use App\Models\PedidoHerraje;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventarioCatalogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_material_sin_stock_inicial(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('inventario.materiales.store'), $this->validData())
            ->assertRedirect(route('inventario.materiales.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('materials', ['nombre' => 'Triplay', 'stock_actual' => 0]);
        $this->assertDatabaseCount('movimiento_inventarios', 0);
    }

    public function test_crea_material_con_stock_inicial_y_registra_movimiento(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('inventario.materiales.store'), [
            ...$this->validData(),
            'stock_inicial' => 12.5,
        ])->assertSessionHas('success');

        $material = Material::where('nombre', 'Triplay')->firstOrFail();
        $this->assertSame('12.50', $material->stock_actual);
        $this->assertDatabaseHas('movimiento_inventarios', [
            'material_id' => $material->id,
            'tipo' => 'entrada',
            'cantidad' => 12.5,
            'motivo' => 'Stock inicial',
            'registrado_por' => $user->id,
        ]);
    }

    public function test_valida_datos_del_material(): void
    {
        $this->actingAs(User::factory()->create());
        Material::create([...$this->validData(), 'stock_actual' => 0]);

        foreach ([
            [['nombre' => ''], 'nombre'],
            [['nombre' => 'Triplay'], 'nombre'],
            [['tipo' => 'otro'], 'tipo'],
            [['unidad_medida' => 'kg'], 'unidad_medida'],
            [['stock_minimo' => -1], 'stock_minimo'],
            [['stock_inicial' => -1], 'stock_inicial'],
        ] as [$cambio, $campo]) {
            $this->post(route('inventario.materiales.store'), [...$this->validData(), ...$cambio])
                ->assertSessionHasErrors($campo);
        }
    }

    public function test_edita_material_sin_cambiar_stock_actual(): void
    {
        $material = $this->createMaterial(stock: 18);

        $this->actingAs(User::factory()->create())
            ->put(route('inventario.materiales.update', $material), [
                'nombre' => 'Triplay premium',
                'tipo' => 'acabado',
                'unidad_medida' => 'litro',
                'stock_minimo' => 3,
                'stock_actual' => 999,
            ])->assertSessionHas('success');

        $material->refresh();
        $this->assertSame('18.00', $material->stock_actual);
        $this->assertSame('Triplay premium', $material->nombre);
        $this->assertSame('acabado', $material->tipo);
    }

    public function test_bloquea_cambio_de_tipo_si_existen_movimientos(): void
    {
        $user = User::factory()->create();
        $material = $this->createMaterial(stock: 5);
        MovimientoInventario::create([
            'material_id' => $material->id,
            'tipo' => 'entrada',
            'cantidad' => 5,
            'motivo' => 'Prueba',
            'registrado_por' => $user->id,
            'fecha' => now()->toDateString(),
        ]);

        $this->actingAs($user)->put(route('inventario.materiales.update', $material), [
            ...$this->validData(),
            'tipo' => 'herraje',
        ])->assertSessionHasErrors('tipo');

        $this->assertSame('madera', $material->fresh()->tipo);
    }

    public function test_da_de_baja_y_restaura_material(): void
    {
        $material = $this->createMaterial();
        $this->actingAs(User::factory()->create());

        $this->delete(route('inventario.materiales.destroy', $material))->assertSessionHas('success');
        $this->assertSoftDeleted($material);

        $this->post(route('inventario.materiales.restore', $material->id))->assertSessionHas('success');
        $this->assertNotSoftDeleted($material);
    }

    public function test_no_restaura_si_ya_existe_material_activo_con_el_mismo_nombre(): void
    {
        $material = $this->createMaterial();
        $material->delete();
        $this->createMaterial();

        $this->actingAs(User::factory()->create())
            ->post(route('inventario.materiales.restore', $material->id))
            ->assertSessionHas('error');

        $this->assertSoftDeleted($material);
    }

    public function test_material_dado_de_baja_no_aparece_en_formulario_de_movimientos(): void
    {
        $activo = $this->createMaterial('Material activo');
        $eliminado = $this->createMaterial('Material dado de baja');
        $eliminado->delete();

        $this->actingAs(User::factory()->create())
            ->get(route('inventario.movimientos.create'))
            ->assertOk()
            ->assertSee($activo->nombre)
            ->assertDontSee($eliminado->nombre);
    }

    public function test_no_da_de_baja_material_asociado_a_un_pedido(): void
    {
        $material = $this->createMaterial('Herraje asociado');
        $cliente = Cliente::create(['nombre' => 'Cliente prueba', 'telefono' => '555-0100']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'fecha_entrega' => now()->addWeek()->toDateString(),
            'estado' => 'pendiente',
            'total' => 100,
            'direccion' => 'Taller',
            'forma_pago' => 'Liquidado',
        ]);
        PedidoHerraje::create([
            'pedido_id' => $pedido->id,
            'material_id' => $material->id,
            'cantidad' => 1,
        ]);

        $this->actingAs(User::factory()->create())
            ->delete(route('inventario.materiales.destroy', $material))
            ->assertRedirect(route('inventario.materiales.index'))
            ->assertSessionHas('error', 'No se puede dar de baja el material porque está asociado a pedidos.');

        $this->assertNotSoftDeleted($material);
    }

    public function test_material_con_solo_movimientos_se_da_de_baja_y_conserva_su_nombre_en_el_historial(): void
    {
        $user = User::factory()->create();
        $material = $this->createMaterial('Material con historial', 5);
        MovimientoInventario::create([
            'material_id' => $material->id,
            'tipo' => 'entrada',
            'cantidad' => 5,
            'motivo' => 'Entrada de prueba',
            'registrado_por' => $user->id,
            'fecha' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->delete(route('inventario.materiales.destroy', $material))
            ->assertSessionHas('success');

        $this->assertSoftDeleted($material);
        $this->get(route('inventario.movimientos.index'))
            ->assertOk()
            ->assertSee('Material con historial');
    }

    public function test_puede_anular_movimiento_de_material_dado_de_baja(): void
    {
        $user = User::factory()->create();
        $material = $this->createMaterial('Material inactivo', 5);
        $movimiento = MovimientoInventario::create([
            'material_id' => $material->id,
            'tipo' => 'entrada',
            'cantidad' => 2,
            'motivo' => 'Entrada de prueba',
            'registrado_por' => $user->id,
            'fecha' => now()->toDateString(),
        ]);
        $material->delete();

        $this->actingAs($user)
            ->delete(route('inventario.movimientos.destroy', $movimiento))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($movimiento);
    }

    public function test_rol_no_permitido_no_puede_acceder_al_catalogo(): void
    {
        $this->actingAs(User::factory()->chofer()->create())
            ->get(route('inventario.materiales.index'))
            ->assertForbidden();
    }

    public function test_usuario_sin_sesion_es_redirigido_al_inicio_de_sesion(): void
    {
        $this->get(route('inventario.materiales.index'))->assertRedirect(route('login'));
    }

    private function validData(): array
    {
        return [
            'nombre' => 'Triplay',
            'tipo' => 'madera',
            'unidad_medida' => 'pieza',
            'stock_minimo' => 2,
        ];
    }

    private function createMaterial(string $nombre = 'Triplay', float $stock = 0): Material
    {
        return Material::create([
            ...$this->validData(),
            'nombre' => $nombre,
            'stock_actual' => $stock,
        ]);
    }
}
