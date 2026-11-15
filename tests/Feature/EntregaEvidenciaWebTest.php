<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\EntregaFoto;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntregaEvidenciaWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidencias');
    }

    public function test_administrador_ve_evidencia_de_entrega_con_firma(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega(Entrega::ESTADO_ENTREGADA, [
            'receptor_nombre' => 'María Receptora',
            'firma_path' => 'entregas/1/firma/firma.png',
        ]);
        $foto = EntregaFoto::create([
            'entrega_id' => $entrega->id,
            'ruta' => 'entregas/1/fotos/foto.png',
        ]);

        $this->get(route('entregas.evidencia', $entrega))
            ->assertOk()
            ->assertSee('María Receptora')
            ->assertSee(route('entregas.evidencia.firma', $entrega))
            ->assertSee(route('entregas.evidencia.foto', [$entrega, $foto]))
            ->assertDontSee($entrega->firma_path)
            ->assertDontSee($foto->ruta);
    }

    public function test_entrega_sin_firma_muestra_motivo(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega(Entrega::ESTADO_ENTREGADA, [
            'receptor_nombre' => 'Juan Receptor',
            'sin_firma' => true,
            'motivo_sin_firma' => 'El receptor no pudo firmar',
        ]);
        EntregaFoto::create(['entrega_id' => $entrega->id, 'ruta' => 'entregas/1/fotos/foto.png']);

        $this->get(route('entregas.evidencia', $entrega))
            ->assertOk()
            ->assertSee('Sin firma')
            ->assertSee('El receptor no pudo firmar');
    }

    public function test_entrega_con_incidencia_muestra_el_motivo(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega(Entrega::ESTADO_INCIDENCIA, [
            'incidencia_motivo' => 'El cliente no estaba disponible',
        ]);

        $this->get(route('entregas.evidencia', $entrega))
            ->assertOk()
            ->assertSee('El cliente no estaba disponible')
            ->assertSee($entrega->estado_fecha_hora->format('d/m/Y H:i'));
    }

    public function test_entregas_sin_evidencia_muestran_mensaje_claro(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([Entrega::ESTADO_PENDIENTE, Entrega::ESTADO_EN_CAMINO, Entrega::ESTADO_ENTREGADA, Entrega::ESTADO_INCIDENCIA] as $estado) {
            $entrega = $this->crearEntrega($estado);
            $this->get(route('entregas.evidencia', $entrega))
                ->assertOk()
                ->assertSee('Esta entrega todavía no tiene evidencia registrada.');
        }
    }

    public function test_carga_evidencia_de_entrega_con_pedido_eliminado(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega();
        $entrega->pedido->delete();

        $this->get(route('entregas.evidencia', $entrega))
            ->assertOk()
            ->assertSee('#'.$entrega->pedido_id)
            ->assertSee('Cliente de evidencia');
    }

    public function test_administrador_descarga_firma_y_foto_privadas(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega(Entrega::ESTADO_ENTREGADA, [
            'receptor_nombre' => 'Receptor',
            'firma_path' => 'entregas/1/firma/firma.png',
        ]);
        $foto = EntregaFoto::create([
            'entrega_id' => $entrega->id,
            'ruta' => 'entregas/1/fotos/foto.png',
        ]);
        Storage::disk('evidencias')->put($entrega->firma_path, $this->png());
        Storage::disk('evidencias')->put($foto->ruta, $this->png());

        foreach ([route('entregas.evidencia.firma', $entrega), route('entregas.evidencia.foto', [$entrega, $foto])] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png')
                ->assertHeader('Cache-Control', 'private')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertStreamedContent($this->png());
        }
    }

    public function test_rechaza_foto_de_otra_entrega_y_firma_inexistente(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega();
        $otraEntrega = $this->crearEntrega();
        $fotoAjena = EntregaFoto::create([
            'entrega_id' => $otraEntrega->id,
            'ruta' => 'entregas/2/fotos/foto.png',
        ]);

        $this->get(route('entregas.evidencia.foto', [$entrega, $fotoAjena]))->assertNotFound();
        $this->get(route('entregas.evidencia.firma', $entrega))->assertNotFound();
    }

    public function test_archivo_faltante_en_disco_responde_404(): void
    {
        $this->actingAs(User::factory()->create());
        $entrega = $this->crearEntrega(Entrega::ESTADO_ENTREGADA, [
            'firma_path' => 'entregas/1/firma/inexistente.png',
        ]);
        $foto = EntregaFoto::create([
            'entrega_id' => $entrega->id,
            'ruta' => 'entregas/1/fotos/inexistente.png',
        ]);

        $this->get(route('entregas.evidencia.firma', $entrega))->assertNotFound();
        $this->get(route('entregas.evidencia.foto', [$entrega, $foto]))->assertNotFound();
    }

    public function test_parametros_no_numericos_responden_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/entregas/no-numerica/evidencia')->assertNotFound();
        $this->get('/entregas/1/evidencia/fotos/no-numerica')->assertNotFound();
    }

    public function test_invitado_es_redirigido_en_las_tres_rutas(): void
    {
        foreach ($this->rutasProtegidas() as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_encargado_y_chofer_no_acceden_a_las_tres_rutas(): void
    {
        foreach ([User::factory()->encargadoInventario()->create(), User::factory()->chofer()->create()] as $usuario) {
            $this->actingAs($usuario);
            foreach ($this->rutasProtegidas() as $url) {
                $this->get($url)->assertForbidden();
            }
        }
    }

    public function test_listado_muestra_acciones_segun_el_estado(): void
    {
        $this->actingAs(User::factory()->create());
        $pendiente = $this->crearEntrega(Entrega::ESTADO_PENDIENTE);
        $enCamino = $this->crearEntrega(Entrega::ESTADO_EN_CAMINO);
        $entregada = $this->crearEntrega(Entrega::ESTADO_ENTREGADA);
        $incidencia = $this->crearEntrega(Entrega::ESTADO_INCIDENCIA);

        $this->get(route('entregas.index'))
            ->assertOk()
            ->assertSee('Ver evidencia')
            ->assertSee('Editar')
            ->assertSee(route('entregas.edit', $pendiente))
            ->assertSee(route('entregas.evidencia', $entregada))
            ->assertSee(route('entregas.evidencia', $incidencia))
            ->assertDontSee(route('entregas.evidencia', $pendiente))
            ->assertDontSee(route('entregas.evidencia', $enCamino));
    }

    private function crearEntrega(string $estado = Entrega::ESTADO_PENDIENTE, array $datos = []): Entrega
    {
        $cliente = Cliente::create(['nombre' => 'Cliente de evidencia']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => now()->toDateString(),
            'direccion' => 'Calle de evidencia 52',
            'estado' => 'pendiente',
            'total' => 100,
        ]);

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => User::factory()->chofer()->create()->id,
            'fecha_asignada' => now()->toDateString(),
            'estado' => $estado,
            ...$datos,
        ]);
    }

    private function rutasProtegidas(): array
    {
        return [
            '/entregas/1/evidencia',
            '/entregas/1/evidencia/firma',
            '/entregas/1/evidencia/fotos/1',
        ];
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
