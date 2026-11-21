<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProblemasEntregasPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceso_al_pdf_por_rol(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('reportes.problemas-entregas', [
            'desde' => '2026-11-01',
            'hasta' => '2026-11-30',
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=problemas-entregas.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        foreach ([User::factory()->encargadoInventario()->create(), User::factory()->chofer()->create()] as $user) {
            $this->actingAs($user)->get(route('reportes.problemas-entregas'))->assertForbidden();
        }

        auth()->logout();
        $this->get(route('reportes.problemas-entregas'))->assertRedirect(route('login'));
    }

    public function test_valida_orden_formato_y_duracion_del_periodo(): void
    {
        $this->actingAs(User::factory()->create());

        $this->from(route('reportes.index'))->get(route('reportes.problemas-entregas', [
            'desde' => '2026-11-20',
            'hasta' => '2026-11-01',
        ]))->assertRedirect(route('reportes.index'))->assertSessionHasErrors('hasta');

        $this->get(route('reportes.problemas-entregas', [
            'desde' => 'fecha-invalida',
            'hasta' => '2026-11-30',
        ]))->assertRedirect(route('reportes.index'))->assertSessionHasErrors('desde');

        $this->get(route('reportes.problemas-entregas', [
            'desde' => '2025-01-01',
            'hasta' => '2026-01-02',
        ]))->assertRedirect(route('reportes.index'))->assertSessionHasErrors('hasta');
    }

    public function test_sin_parametros_usa_el_mes_en_curso_en_la_zona_de_reportes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00', config('reportes.zona_horaria')));
        config(['app.timezone' => 'UTC']);
        $this->mock(EntregaRepositoryInterface::class, function ($mock): void {
            $mock->shouldReceive('getIncidenciasConSituacion')
                ->once()
                ->withArgs(fn ($desde, $hasta): bool => $desde->format('Y-m-d H:i:s T') === '2026-11-01 06:00:00 UTC'
                    && $hasta->format('Y-m-d H:i:s T') === '2026-12-01 05:59:59 UTC')
                ->andReturn(new Collection);
        });

        try {
            $response = $this->actingAs(User::factory()->create())->get(route('reportes.problemas-entregas'));

            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_repositorio_filtra_limites_y_deriva_las_tres_situaciones_sin_excluir_pedido_eliminado(): void
    {
        $sinAtender = $this->incidencia('2026-11-01 00:00:00', 'Cliente Ñandú', 'Chofer Ágil', 'No abrió el portón');
        $sinAtender->pedido->delete();

        $reasignada = $this->incidencia('2026-11-10 10:00:00', 'Cliente Reasignado');
        $this->intentoPosterior($reasignada, Entrega::ESTADO_PENDIENTE);

        $resuelta = $this->incidencia('2026-11-15 11:00:00', 'Cliente Resuelto');
        $this->intentoPosterior($resuelta, Entrega::ESTADO_ENTREGADA);

        $otraIncidencia = $this->incidencia('2026-11-20 12:00:00', 'Cliente con dos intentos');
        $segundaIncidencia = $this->intentoPosterior($otraIncidencia, Entrega::ESTADO_INCIDENCIA, '2026-11-21 13:00:00');

        $ultimoDia = $this->incidencia('2026-11-30 23:59:59', 'Cliente Límite');
        $fuera = $this->incidencia('2026-12-01 00:00:00', 'Cliente Fuera');
        $noIncidencia = $this->crearEntregaParaPedido($fuera->pedido, Entrega::ESTADO_ENTREGADA, '2026-11-12 10:00:00');

        $incidencias = app(EntregaRepositoryInterface::class)->getIncidenciasConSituacion(
            Carbon::parse('2026-11-01 00:00:00'),
            Carbon::parse('2026-11-30 23:59:59'),
        );

        $this->assertTrue($incidencias->contains('id', $sinAtender->id));
        $this->assertTrue($incidencias->contains('id', $ultimoDia->id));
        $this->assertTrue($incidencias->contains('id', $segundaIncidencia->id));
        $this->assertFalse($incidencias->contains('id', $fuera->id));
        $this->assertFalse($incidencias->contains('id', $noIncidencia->id));
        $this->assertSame('Sin atender', $incidencias->firstWhere('id', $sinAtender->id)->situacion);
        $this->assertSame('Reasignada', $incidencias->firstWhere('id', $reasignada->id)->situacion);
        $this->assertSame('Resuelta', $incidencias->firstWhere('id', $resuelta->id)->situacion);
        $this->assertSame('Reasignada', $incidencias->firstWhere('id', $otraIncidencia->id)->situacion);
        $this->assertSame('Sin atender', $incidencias->firstWhere('id', $segundaIncidencia->id)->situacion);
        $this->assertSame('Cliente Ñandú', $incidencias->firstWhere('id', $sinAtender->id)->pedido->cliente->nombre);
    }

    public function test_vista_muestra_periodo_datos_resumen_y_textos_vacios(): void
    {
        $incidencias = new Collection([
            $this->incidencia('2026-11-01 08:15:00', 'Cliente Cañón', 'Chofer Ñuñoa', 'Dirección incorrecta', null)->setAttribute('situacion', 'Sin atender'),
            $this->incidencia('2026-11-10 09:30:00', 'Cliente Dos')->setAttribute('situacion', 'Reasignada'),
            $this->incidencia('2026-11-30 18:45:00', 'Cliente Tres')->setAttribute('situacion', 'Resuelta'),
        ]);
        $html = $this->renderizar($incidencias, '2026-11-01', '2026-11-30');

        foreach (['Periodo: 01/11/2026 al 30/11/2026', 'Total de incidencias: 3', 'Sin atender: 1', 'Reasignadas: 1', 'Resueltas: 1', 'Cliente Cañón', 'Chofer Ñuñoa', 'Dirección incorrecta', '01/11/2026 08:15', 'Situación'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringContainsString('<td>—</td>', $html);
    }

    public function test_vista_sin_incidencias_muestra_mensaje_en_lugar_de_tabla(): void
    {
        $html = $this->renderizar(new Collection, '2026-11-01', '2026-11-30');

        $this->assertStringContainsString('Total de incidencias: 0', $html);
        $this->assertStringContainsString('No hay incidencias de entrega en el periodo seleccionado.', $html);
        $this->assertStringNotContainsString('<table class="report-table">', $html);
    }

    public function test_formulario_solo_se_muestra_a_admin(): void
    {
        $admin = $this->actingAs(User::factory()->create())->get(route('reportes.index'))->assertOk();
        $admin->assertSee('Generar Problemas con entregas PDF')
            ->assertSee('action="'.route('reportes.problemas-entregas').'"', false)
            ->assertSee('target="_blank"', false);

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertDontSee('Generar Problemas con entregas PDF')
            ->assertDontSee('action="'.route('reportes.problemas-entregas').'"', false);
    }

    private function incidencia(
        string $fecha,
        string $cliente = 'Cliente de prueba',
        string $chofer = 'Chofer de prueba',
        string $motivo = 'Cliente ausente',
        ?string $accion = 'Reprogramar visita',
    ): Entrega {
        $clienteModel = Cliente::create(['nombre' => $cliente]);
        $pedido = Pedido::create([
            'cliente_id' => $clienteModel->id,
            'fecha_pedido' => '2026-10-01',
            'direccion' => 'Calle de prueba',
            'estado' => 'completado',
            'total' => 100,
        ]);
        $entrega = Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => User::factory()->chofer()->create(['name' => $chofer])->id,
            'fecha_asignada' => '2026-11-01',
            'estado' => Entrega::ESTADO_INCIDENCIA,
            'estado_fecha_hora' => $fecha,
            'incidencia_motivo' => $motivo,
            'accion_siguiente' => $accion,
        ]);

        return $entrega->load(['pedido.cliente', 'chofer']);
    }

    private function intentoPosterior(Entrega $incidencia, string $estado, string $fecha = '2026-11-25 10:00:00'): Entrega
    {
        return $this->crearEntregaParaPedido($incidencia->pedido, $estado, $fecha);
    }

    private function crearEntregaParaPedido(Pedido $pedido, string $estado, string $fecha): Entrega
    {
        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => User::factory()->chofer()->create()->id,
            'fecha_asignada' => '2026-11-02',
            'estado' => $estado,
            'estado_fecha_hora' => $fecha,
            'incidencia_motivo' => $estado === Entrega::ESTADO_INCIDENCIA ? 'Segunda incidencia' : null,
        ]);
    }

    private function renderizar(Collection $incidencias, string $desde, string $hasta): string
    {
        $this->actingAs(User::factory()->create());
        $resumen = [
            'total' => $incidencias->count(),
            'sinAtender' => $incidencias->where('situacion', 'Sin atender')->count(),
            'reasignadas' => $incidencias->where('situacion', 'Reasignada')->count(),
            'resueltas' => $incidencias->where('situacion', 'Resuelta')->count(),
        ];

        return view('reportes.problemas-entregas', [
            'incidencias' => $incidencias,
            'resumen' => $resumen,
            'desde' => Carbon::parse($desde),
            'hasta' => Carbon::parse($hasta)->endOfDay(),
        ])->render();
    }
}
