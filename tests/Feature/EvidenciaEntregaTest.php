<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\EntregaFoto;
use App\Models\Pedido;
use App\Models\User;
use App\Services\EvidenciaEntregaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class EvidenciaEntregaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidencias');
    }

    public function test_registra_entrega_completa_con_fotos_y_firma(): void
    {
        $entrega = $this->crearEntrega()->fresh();
        $estado = $entrega->estado;
        $fechaEstado = $entrega->estado_fecha_hora->toDateTimeString();

        $resultado = $this->servicio()->registrarEntrega(
            $entrega,
            '  María López  ',
            [$this->foto()],
            UploadedFile::fake()->create('firma.png', 100, 'image/png'),
        );

        $this->assertSame('María López', $resultado->fresh()->receptor_nombre);
        $this->assertFalse($resultado->fresh()->sin_firma);
        $this->assertNotNull($resultado->fresh()->firma_path);
        $this->assertCount(1, $resultado->fresh()->fotos);
        Storage::disk('evidencias')->assertExists($resultado->fresh()->firma_path);
        Storage::disk('evidencias')->assertExists($resultado->fresh()->fotos[0]->ruta);
        $this->assertSame($estado, $resultado->fresh()->estado);
        $this->assertSame($fechaEstado, $resultado->fresh()->estado_fecha_hora->toDateTimeString());
    }

    public function test_registra_entrega_sin_firma_con_motivo(): void
    {
        $resultado = $this->servicio()->registrarEntrega($this->crearEntrega(), 'Receptor', [$this->foto()], null, 'No pudo firmar');

        $this->assertTrue($resultado->fresh()->sin_firma);
        $this->assertSame('No pudo firmar', $resultado->fresh()->motivo_sin_firma);
        $this->assertNull($resultado->fresh()->firma_path);
        $this->assertCount(1, $resultado->fresh()->fotos);
    }

    public function test_registra_motivo_de_incidencia(): void
    {
        $resultado = $this->servicio()->registrarIncidencia($this->crearEntrega(), 'Cliente no localizado');

        $this->assertSame('Cliente no localizado', $resultado->fresh()->incidencia_motivo);
    }

    public function test_relaciones_y_casts_de_evidencia(): void
    {
        $entrega = $this->servicio()->registrarEntrega($this->crearEntrega(), 'Receptor', [$this->foto()], null, 'No quiso firmar');
        $entrega->regresa_a_stock = false;
        $entrega->save();
        $entrega->refresh();

        $this->assertTrue($entrega->sin_firma);
        $this->assertIsBool($entrega->regresa_a_stock);
        $this->assertInstanceOf(EntregaFoto::class, $entrega->fotos->first());
        $this->assertTrue($entrega->fotos->first()->entrega->is($entrega));
    }

    public function test_rechaza_datos_invalidos_sin_registros_ni_archivos(): void
    {
        $casos = [
            ['receptor_nombre' => ' ', 'fotos' => [$this->foto()], 'firma' => $this->firma()],
            ['receptor_nombre' => 'Receptor', 'fotos' => [], 'firma' => $this->firma()],
            ['receptor_nombre' => 'Receptor', 'fotos' => [$this->foto(), $this->foto(), $this->foto(), $this->foto()], 'firma' => $this->firma()],
            ['receptor_nombre' => 'Receptor', 'fotos' => [$this->foto()]],
            ['receptor_nombre' => 'Receptor', 'fotos' => [$this->foto()], 'motivo_sin_firma' => 'corto'],
            ['receptor_nombre' => 'Receptor', 'fotos' => [$this->foto()], 'firma' => $this->firma(), 'motivo_sin_firma' => 'Motivo suficientemente largo'],
        ];

        foreach ($casos as $caso) {
            $entrega = $this->crearEntrega();
            try {
                $this->servicio()->registrarEntrega(
                    $entrega,
                    $caso['receptor_nombre'],
                    $caso['fotos'],
                    $caso['firma'] ?? null,
                    $caso['motivo_sin_firma'] ?? null,
                );
                $this->fail('Se esperaba un error de validación.');
            } catch (ValidationException) {
                $this->assertDatabaseMissing('entrega_fotos', ['entrega_id' => $entrega->id]);
                $this->assertNull($entrega->fresh()->receptor_nombre);
                $this->assertSame([], Storage::disk('evidencias')->allFiles());
            }
        }
    }

    public function test_rechaza_incidencia_vacia_o_corta(): void
    {
        foreach (['', '  corto  '] as $motivo) {
            $entrega = $this->crearEntrega();
            try {
                $this->servicio()->registrarIncidencia($entrega, $motivo);
                $this->fail('Se esperaba un error de validación.');
            } catch (ValidationException) {
                $this->assertNull($entrega->fresh()->incidencia_motivo);
            }
        }
    }

    public function test_rechaza_entrega_con_evidencia_previa(): void
    {
        $entrega = $this->crearEntrega();
        $entrega->update(['incidencia_motivo' => 'Incidencia ya registrada']);

        try {
            $this->servicio()->registrarEntrega($entrega, 'Receptor', [$this->foto()], $this->firma());
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $exception) {
            $this->assertSame('Esta entrega ya tiene evidencia registrada.', $exception->errors()['evidencia'][0]);
            $this->assertSame([], Storage::disk('evidencias')->allFiles());
        }
    }

    public function test_borra_archivos_si_falla_la_persistencia(): void
    {
        EntregaFoto::creating(function (): void {
            throw new RuntimeException('Fallo simulado de base de datos.');
        });

        $entrega = $this->crearEntrega();
        try {
            $this->servicio()->registrarEntrega($entrega, 'Receptor', [$this->foto()], $this->firma());
            $this->fail('Se esperaba la excepción simulada.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado de base de datos.', $exception->getMessage());
            $this->assertNull($entrega->fresh()->receptor_nombre);
            $this->assertDatabaseMissing('entrega_fotos', ['entrega_id' => $entrega->id]);
            $this->assertSame([], Storage::disk('evidencias')->allFiles());
        } finally {
            EntregaFoto::flushEventListeners();
        }
    }

    public function test_disco_de_evidencias_es_privado_y_esta_fuera_de_public(): void
    {
        $root = config('filesystems.disks.evidencias.root');

        $this->assertStringStartsWith(storage_path('app/private/evidencias'), $root);
        $this->assertStringNotContainsString(public_path(), $root);
        $this->assertFalse(config('filesystems.disks.evidencias.serve'));
    }

    private function servicio(): EvidenciaEntregaService
    {
        return app(EvidenciaEntregaService::class);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg');
    }

    private function firma(): UploadedFile
    {
        return UploadedFile::fake()->create('firma.png', 100, 'image/png');
    }

    private function crearEntrega(): Entrega
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de prueba']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'direccion' => 'Calle de prueba',
            'estado' => 'pendiente',
            'total' => 100,
        ]);
        $chofer = User::factory()->create(['rol' => 'chofer']);

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => now()->toDateString(),
        ]);
    }
}
