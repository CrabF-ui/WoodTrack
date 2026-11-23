<?php

namespace Tests\Feature;

use App\Models\Entrega;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Database\Seeders\DemoEscenariosRevertirSeeder;
use Database\Seeders\DemoEscenariosSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoEscenariosSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn (): string => 'local');
        Carbon::setTestNow(Carbon::parse('2026-11-23 10:00:00', config('app.timezone')));
        $this->seed(UserSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_crea_los_escenarios_clave_y_es_idempotente(): void
    {
        $this->artisan('db:seed', ['--class' => DemoEscenariosSeeder::class])->assertSuccessful();

        $pedidosOctubre = Pedido::query()
            ->where('direccion', 'like', DemoEscenariosSeeder::PREFIJO.' OCT-%')
            ->whereBetween('fecha_pedido', ['2026-10-01', '2026-10-31']);
        $totalValido = (clone $pedidosOctubre)->where('estado', '!=', 'cancelado')->sum('total');

        $this->assertGreaterThan(0, (float) $totalValido);
        $this->assertSame(9, (clone $pedidosOctubre)->where('estado', '!=', 'cancelado')->count());
        $this->assertSame(100000.0, (float) $totalValido);
        $this->assertSame(1, (clone $pedidosOctubre)->where('estado', 'cancelado')->count());
        $this->assertSame(100000.0, app(PedidoRepositoryInterface::class)->getVentasMes(Carbon::parse('2026-10-01'))['total']);

        $incidencias = app(EntregaRepositoryInterface::class)->getIncidenciasConSituacion(
            Carbon::parse('2026-11-01 00:00:00'),
            Carbon::parse('2026-11-30 23:59:59'),
        )->filter(fn (Entrega $entrega) => str_starts_with((string) $entrega->notas, DemoEscenariosSeeder::PREFIJO.' INC-'));

        $this->assertTrue($incidencias->contains('situacion', 'Sin atender'));
        $this->assertTrue($incidencias->contains('situacion', 'Reasignada'));
        $this->assertTrue($incidencias->contains('situacion', 'Resuelta'));
        $this->assertTrue(Material::query()->where('nombre', DemoEscenariosSeeder::PREFIJO.' Bisagra bajo stock')->bajoStock()->exists());

        $pedidosDemo = Pedido::query()->where('direccion', 'like', DemoEscenariosSeeder::PREFIJO.'%');
        $this->assertSame(2, (clone $pedidosDemo)->completadosSinEntrega()->count());
        $this->assertSame(1, (clone $pedidosDemo)->completadosSinHerrajes()->count());

        $conteos = [Pedido::count(), Entrega::count(), Material::count()];
        $this->artisan('db:seed', ['--class' => DemoEscenariosSeeder::class])->assertSuccessful();

        $this->assertSame($conteos, [Pedido::count(), Entrega::count(), Material::count()]);
    }

    public function test_revertir_elimina_todos_los_datos_demo_y_restaura_los_conteos(): void
    {
        $materialReal = Material::create([
            'nombre' => 'Material real de control',
            'tipo' => 'herraje',
            'unidad_medida' => 'pieza',
            'stock_actual' => 50,
            'stock_minimo' => 5,
        ]);
        $tablas = ['clientes', 'materials', 'pedidos', 'detalle_pedidos', 'pedido_herrajes', 'entregas', 'movimiento_inventarios'];
        $conteosIniciales = collect($tablas)->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()]);

        $this->artisan('db:seed', ['--class' => DemoEscenariosSeeder::class])->assertSuccessful();
        $this->assertGreaterThan(0, Pedido::query()->where('direccion', 'like', DemoEscenariosSeeder::PREFIJO.'%')->count());
        $this->actingAs(User::query()->where('email', 'admin@woodtrack.test')->firstOrFail());
        $pedidoDemo = Pedido::query()->where('direccion', DemoEscenariosSeeder::PREFIJO.' OCT-0')->firstOrFail();
        app(PedidoRepositoryInterface::class)->update($pedidoDemo->id, [
            'herrajes' => [['material_id' => $materialReal->id, 'cantidad' => 3]],
        ]);
        $this->assertSame(47.0, (float) $materialReal->refresh()->stock_actual);

        $this->artisan('db:seed', ['--class' => DemoEscenariosRevertirSeeder::class])->assertSuccessful();

        foreach ($conteosIniciales as $tabla => $conteo) {
            $this->assertSame($conteo, DB::table($tabla)->count(), "El conteo de {$tabla} debe volver a su valor inicial.");
        }
        $this->assertFalse(Pedido::withTrashed()->where('direccion', 'like', DemoEscenariosSeeder::PREFIJO.'%')->exists());
        $this->assertFalse(Material::withTrashed()->where('nombre', 'like', DemoEscenariosSeeder::PREFIJO.'%')->exists());
        $this->assertSame(50.0, (float) $materialReal->refresh()->stock_actual);
    }

    public function test_funciona_con_usuarios_de_roles_requeridos_aunque_tengan_otros_correos(): void
    {
        User::query()->where('rol', User::ROL_ADMIN)->firstOrFail()->update(['email' => 'administracion@ejemplo.test']);
        User::query()->where('rol', User::ROL_CHOFER)->firstOrFail()->update(['email' => 'repartidor@ejemplo.test']);

        $this->artisan('db:seed', ['--class' => DemoEscenariosSeeder::class])->assertSuccessful();

        $this->assertTrue(Pedido::query()->where('direccion', 'like', DemoEscenariosSeeder::PREFIJO.'%')->exists());
        $this->assertTrue(Entrega::query()
            ->where('notas', 'like', DemoEscenariosSeeder::PREFIJO.'%')
            ->whereHas('chofer', fn ($query) => $query->where('email', 'repartidor@ejemplo.test'))
            ->exists());
    }
}
