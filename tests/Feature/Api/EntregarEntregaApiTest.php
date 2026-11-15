<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Pedido;
use App\Models\User;
use App\Services\EvidenciaEntregaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class EntregarEntregaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidencias');
    }

    public function test_registra_entrega_con_firma_desde_en_camino(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        $ahora = Carbon::parse('2035-11-11 12:00:00');
        Carbon::setTestNow($ahora);
        Sanctum::actingAs($entrega->chofer);

        try {
            $this->post($this->ruta($entrega), [
                'receptor_nombre' => 'María López',
                'fotos' => [$this->foto('frente.jpg'), $this->foto('producto.png')],
                'firma' => $this->foto('firma.png'),
            ], ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJsonPath('data.estado', Entrega::ESTADO_ENTREGADA);

            $entrega->refresh();
            $this->assertSame(Entrega::ESTADO_ENTREGADA, $entrega->estado);
            $this->assertSame($ahora->toDateTimeString(), $entrega->estado_fecha_hora->toDateTimeString());
            $this->assertNotSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
            $this->assertSame('María López', $entrega->receptor_nombre);
            $this->assertFalse($entrega->sin_firma);
            $this->assertNull($entrega->motivo_sin_firma);
            $this->assertNotNull($entrega->firma_path);
            $this->assertCount(2, $entrega->fotos);
            Storage::disk('evidencias')->assertExists($entrega->firma_path);
            foreach ($entrega->fotos as $foto) {
                Storage::disk('evidencias')->assertExists($foto->ruta);
            }
            $this->assertDatabaseCount('entrega_fotos', 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_registra_entrega_sin_firma_con_motivo(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        Sanctum::actingAs($entrega->chofer);

        $this->post($this->ruta($entrega), [
            'receptor_nombre' => 'Receptor Ejemplo',
            'fotos' => [$this->foto()],
            'motivo_sin_firma' => 'El receptor no pudo firmar',
        ], ['Accept' => 'application/json'])->assertOk();

        $entrega->refresh();
        $this->assertSame(Entrega::ESTADO_ENTREGADA, $entrega->estado);
        $this->assertTrue($entrega->sin_firma);
        $this->assertSame('El receptor no pudo firmar', $entrega->motivo_sin_firma);
        $this->assertNull($entrega->firma_path);
        $this->assertCount(1, $entrega->fotos);
        $this->assertCount(1, Storage::disk('evidencias')->allFiles());
    }

    public function test_acepta_una_dos_y_tres_fotos(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);

        foreach ([1, 2, 3] as $cantidad) {
            $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
            $fotos = [];
            for ($indice = 1; $indice <= $cantidad; $indice++) {
                $fotos[] = $this->foto("foto-{$cantidad}-{$indice}.jpg");
            }

            $this->post($this->ruta($entrega), [
                'receptor_nombre' => 'Receptor Ejemplo',
                'fotos' => $fotos,
                'firma' => $this->foto("firma-{$cantidad}.png"),
            ], ['Accept' => 'application/json'])->assertOk();

            $this->assertCount($cantidad, $entrega->fresh()->fotos);
        }
    }

    public function test_rechaza_cero_y_cuatro_fotos_sin_dejar_cambios(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);
        $casos = [
            [[], 'Debe registrar al menos una foto.'],
            [[
                $this->foto('uno.jpg'),
                $this->foto('dos.jpg'),
                $this->foto('tres.jpg'),
                $this->foto('cuatro.jpg'),
            ], 'No puede registrar más de 3 fotos.'],
        ];

        foreach ($casos as [$fotos, $mensaje]) {
            $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
            $this->post($this->ruta($entrega), [
                'receptor_nombre' => 'Receptor Ejemplo',
                'fotos' => $fotos,
                'firma' => $this->foto('firma.png'),
            ], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('fotos')
                ->assertJsonFragment([$mensaje]);

            $this->assertEntregaSinCambios($entrega);
        }
    }

    public function test_rechaza_receptor_ausente_demasiado_corto_o_demasiado_largo(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);

        foreach ([null, 'A', str_repeat('a', 121)] as $receptor) {
            $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
            $datos = $this->datosValidos();
            if ($receptor === null) {
                unset($datos['receptor_nombre']);
            } else {
                $datos['receptor_nombre'] = $receptor;
            }

            $this->post($this->ruta($entrega), $datos, ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('receptor_nombre');

            $this->assertEntregaSinCambios($entrega);
        }
    }

    public function test_rechaza_archivos_invalidos_o_mayores_de_cinco_mb(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);
        $casos = [
            ['fotos' => [UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf')]],
            ['fotos' => [UploadedFile::fake()->createWithContent(
                'foto.gif',
                base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true),
            )]],
            ['fotos' => [$this->foto('grande.jpg')->size(5121)]],
            ['firma' => $this->foto('firma-grande.png')->size(5121)],
        ];

        foreach ($casos as $cambios) {
            $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
            $datos = array_replace($this->datosValidos(), $cambios);

            $this->post($this->ruta($entrega), $datos, ['Accept' => 'application/json'])
                ->assertUnprocessable();

            $this->assertEntregaSinCambios($entrega);
        }
    }

    public function test_rechaza_datos_de_firma_incompatibles_sin_dejar_cambios(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);
        $casos = [
            ['firma' => null, 'motivo_sin_firma' => null],
            ['firma' => null, 'motivo_sin_firma' => 'corto'],
            ['firma' => $this->foto('firma.png'), 'motivo_sin_firma' => 'El receptor no quiso firmar'],
        ];

        foreach ($casos as $caso) {
            $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
            $datos = [
                'receptor_nombre' => 'Receptor Ejemplo',
                'fotos' => [$this->foto()],
            ];
            if ($caso['firma'] !== null) {
                $datos['firma'] = $caso['firma'];
            }
            if ($caso['motivo_sin_firma'] !== null) {
                $datos['motivo_sin_firma'] = $caso['motivo_sin_firma'];
            }

            $this->post($this->ruta($entrega), $datos, ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('motivo_sin_firma');

            $this->assertEntregaSinCambios($entrega);
        }
    }

    public function test_rechaza_entrega_pendiente(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create());
        Sanctum::actingAs($entrega->chofer);

        $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('estado')
            ->assertJsonFragment(['La entrega debe estar en camino para registrarla como entregada.']);

        $this->assertEntregaSinCambios($entrega, Entrega::ESTADO_PENDIENTE);
    }

    public function test_rechaza_entrega_cerrada(): void
    {
        $chofer = User::factory()->chofer()->create();
        Sanctum::actingAs($chofer);

        foreach ([Entrega::ESTADO_ENTREGADA, Entrega::ESTADO_INCIDENCIA] as $estado) {
            $entrega = $this->crearEntrega($chofer, $estado);
            $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('estado')
                ->assertJsonFragment(['Esta entrega ya fue cerrada y no puede cambiar de estado.']);

            $this->assertEntregaSinCambios($entrega, $estado);
        }
    }

    public function test_rechaza_entrega_en_camino_con_evidencia_previa(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $entrega->update(['receptor_nombre' => 'Receptor previo']);
        Sanctum::actingAs($entrega->chofer);

        $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('evidencia')
            ->assertJsonFragment(['Esta entrega ya tiene evidencia registrada.']);

        $entrega->refresh();
        $this->assertSame(Entrega::ESTADO_EN_CAMINO, $entrega->estado);
        $this->assertSame('Receptor previo', $entrega->receptor_nombre);
        $this->assertSame([], Storage::disk('evidencias')->allFiles());
    }

    public function test_no_expone_entregas_ajenas_inexistentes_o_de_pedidos_eliminados(): void
    {
        $chofer = User::factory()->chofer()->create();
        $ajena = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $eliminada = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
        $eliminada->pedido->delete();
        Sanctum::actingAs($chofer);

        foreach ([$ajena, $eliminada] as $entrega) {
            $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
                ->assertNotFound();
            $this->assertEntregaSinCambios($entrega);
        }

        $this->post('/api/entregas/999999/entregar', $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_ruta_requiere_chofer_activo_autenticado(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);

        $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Esta cuenta no tiene acceso a la app.']);

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->chofer()->inactivo()->create());
        $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tu cuenta está desactivada. Contacta a un administrador.']);

        $this->assertEntregaSinCambios($entrega);
    }

    public function test_si_el_servicio_falla_la_transaccion_revierte_el_estado(): void
    {
        $entrega = $this->crearEntrega(User::factory()->chofer()->create(), Entrega::ESTADO_EN_CAMINO);
        $fechaAnterior = $entrega->estado_fecha_hora->toDateTimeString();
        Sanctum::actingAs($entrega->chofer);
        $this->mock(EvidenciaEntregaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('registrarEntrega')->once()
                ->andThrow(new RuntimeException('Fallo simulado del servicio.'));
        });
        $this->withoutExceptionHandling();

        try {
            $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json']);
            $this->fail('Se esperaba el fallo simulado del servicio.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado del servicio.', $exception->getMessage());
        }

        $entrega->refresh();
        $this->assertSame(Entrega::ESTADO_EN_CAMINO, $entrega->estado);
        $this->assertSame($fechaAnterior, $entrega->estado_fecha_hora->toDateTimeString());
        $this->assertNull($entrega->receptor_nombre);
        $this->assertDatabaseMissing('entrega_fotos', ['entrega_id' => $entrega->id]);
        $this->assertSame([], Storage::disk('evidencias')->allFiles());
    }

    public function test_entrega_registrada_deja_de_aparecer_en_la_lista_activa(): void
    {
        $chofer = User::factory()->chofer()->create();
        $entrega = $this->crearEntrega($chofer, Entrega::ESTADO_EN_CAMINO);
        Sanctum::actingAs($chofer);

        $this->post($this->ruta($entrega), $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertOk();
        $this->getJson('/api/entregas')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['id' => $entrega->id]);
    }

    public function test_restringe_el_parametro_de_ruta_a_numeros(): void
    {
        Sanctum::actingAs(User::factory()->chofer()->create());

        $this->post('/api/entregas/no-numerico/entregar', $this->datosValidos(), ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    private function assertEntregaSinCambios(Entrega $entrega, string $estado = Entrega::ESTADO_EN_CAMINO): void
    {
        $entrega->refresh();
        $this->assertSame($estado, $entrega->estado);
        $this->assertNull($entrega->receptor_nombre);
        $this->assertNull($entrega->firma_path);
        $this->assertNull($entrega->motivo_sin_firma);
        $this->assertFalse($entrega->sin_firma);
        $this->assertDatabaseMissing('entrega_fotos', ['entrega_id' => $entrega->id]);
        $this->assertSame([], Storage::disk('evidencias')->allFiles());
    }

    private function datosValidos(): array
    {
        return [
            'receptor_nombre' => 'Receptor Ejemplo',
            'fotos' => [$this->foto()],
            'firma' => $this->foto('firma.png'),
        ];
    }

    private function foto(string $nombre = 'foto.jpg'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        $jpeg = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q==',
            true,
        );
        $contenido = match (strtolower(pathinfo($nombre, PATHINFO_EXTENSION))) {
            'png' => $png,
            'jpg', 'jpeg' => $jpeg,
            default => throw new \InvalidArgumentException('La imagen de prueba debe tener extensión JPG, JPEG o PNG.'),
        };

        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function ruta(Entrega $entrega): string
    {
        return "/api/entregas/{$entrega->id}/entregar";
    }

    private function crearEntrega(
        User $chofer,
        string $estado = Entrega::ESTADO_PENDIENTE,
        string $fecha = '2026-11-11',
    ): Entrega {
        $cliente = Cliente::create(['nombre' => 'Cliente API', 'telefono' => '5550100']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => $fecha,
            'fecha_entrega' => $fecha,
            'estado' => 'pendiente',
            'total' => 100,
            'direccion' => 'Calle API 10',
        ]);

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => $fecha,
            'orden' => 1,
            'estado' => $estado,
        ]);
    }
}
