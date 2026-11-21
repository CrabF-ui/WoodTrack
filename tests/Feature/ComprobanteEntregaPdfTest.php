<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\EntregaFoto;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Services\EvidenciaEntregaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComprobanteEntregaPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('evidencias');
    }

    public function test_acceso_al_comprobante_por_rol(): void
    {
        $entrega = $this->entregaCompleta();

        $response = $this->actingAs(User::factory()->create())->get(route('entregas.comprobante', $entrega));
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', "inline; filename=comprobante-entrega-{$entrega->id}.pdf")
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs(User::factory()->encargadoInventario()->create())
            ->get(route('entregas.comprobante', $entrega))
            ->assertForbidden();
        $this->actingAs(User::factory()->chofer()->create())
            ->get(route('entregas.comprobante', $entrega))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('entregas.comprobante', $entrega))->assertRedirect(route('login'));
    }

    public function test_rechaza_entrega_no_entregada_sin_evidencia_e_inexistente(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([Entrega::ESTADO_PENDIENTE, Entrega::ESTADO_EN_CAMINO, Entrega::ESTADO_INCIDENCIA] as $estado) {
            $this->get(route('entregas.comprobante', $this->crearEntrega($estado)))->assertNotFound();
        }

        $sinEvidencia = $this->crearEntrega(Entrega::ESTADO_ENTREGADA);
        $sinFotos = $this->crearEntrega(Entrega::ESTADO_ENTREGADA, [
            'receptor_nombre' => 'Receptor sin fotos',
            'sin_firma' => true,
            'motivo_sin_firma' => 'No fue posible firmar',
        ]);
        foreach ([$sinEvidencia, $sinFotos] as $entrega) {
            $this->get(route('entregas.comprobante', $entrega))->assertNotFound();
        }
        $this->get(route('entregas.comprobante', 999999))->assertNotFound();
    }

    public function test_vista_muestra_datos_productos_y_receptor_sin_informacion_interna(): void
    {
        $entrega = $this->entregaCompleta();
        $entrega->pedido->detalles()->create([
            'producto' => 'Cómoda para niña',
            'cantidad' => 2,
            'precio_unitario' => 9876.54,
        ]);
        $material = Material::create([
            'nombre' => 'Herraje secreto',
            'tipo' => 'herraje',
            'unidad_medida' => 'pieza',
            'stock_actual' => 10,
            'stock_minimo' => 1,
        ]);
        $entrega->pedido->herrajes()->create(['material_id' => $material->id, 'cantidad' => 4]);

        $html = $this->renderizar($entrega);

        foreach (['María Ñandú', 'Cliente Cañón', 'Avenida Diseño 123', 'Chofer de prueba', 'Cómoda para niña', '<td>2</td>'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        foreach (['9876.54', '9,876.54', 'Herraje secreto', $entrega->firma_path, $entrega->fotos->first()->ruta, 'Forma de pago'] as $texto) {
            $this->assertStringNotContainsString($texto, $html);
        }
    }

    public function test_firma_cifrada_y_antigua_se_incrustan_como_data_uri(): void
    {
        foreach ([true, false] as $cifrada) {
            $entrega = $this->entregaCompleta($cifrada);
            $html = $this->renderizar($entrega);

            $this->assertStringContainsString('data:image/png;base64,', $html);
            $this->assertStringNotContainsString($entrega->firma_path, $html);
        }
    }

    public function test_entrega_sin_firma_muestra_motivo(): void
    {
        $entrega = $this->entregaCompleta(sinFirma: true);
        $html = $this->renderizar($entrega);

        $this->assertStringContainsString('<strong>Sin firma</strong>', $html);
        $this->assertStringContainsString('El receptor no pudo firmar por lesión', $html);
        $this->assertStringNotContainsString('data:image/png;base64,', $html);
    }

    public function test_solo_incrusta_tres_fotos_ordenadas_por_id(): void
    {
        $entrega = $this->entregaCompleta();
        for ($i = 2; $i <= 5; $i++) {
            $ruta = "entregas/{$entrega->id}/fotos/foto-{$i}.png";
            Storage::disk('evidencias')->put($ruta, $this->imagenPng(20 + $i, 20));
            EntregaFoto::create(['entrega_id' => $entrega->id, 'ruta' => $ruta]);
        }

        $html = $this->renderizar($entrega);

        $this->assertSame(3, substr_count($html, 'data:image/jpeg;base64,'));
        $this->assertSame(1, substr_count($html, 'Fotos de la entrega'));
        $this->assertLessThan(strpos($html, 'data:image/jpeg;base64,'), strpos($html, 'Fotos de la entrega'));
    }

    public function test_foto_faltante_no_rompe_pdf_y_muestra_aviso(): void
    {
        $entrega = $this->entregaCompleta();
        Storage::disk('evidencias')->delete($entrega->fotos->first()->ruta);

        $html = $this->renderizar($entrega);
        $this->assertStringContainsString('Foto no disponible', $html);
        $this->assertSame(1, substr_count($html, 'Fotos de la entrega'));
        $this->assertLessThan(strpos($html, 'Foto no disponible'), strpos($html, 'Fotos de la entrega'));

        $response = $this->actingAs(User::factory()->create())->get(route('entregas.comprobante', $entrega));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_foto_grande_se_redimensiona_a_un_maximo_de_800_pixeles(): void
    {
        $entrega = $this->entregaCompleta();
        $foto = $entrega->fotos->first();
        Storage::disk('evidencias')->put($foto->ruta, $this->imagenPng(1200, 600));

        $html = $this->renderizar($entrega);

        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringNotContainsString('width="1200"', $html);
    }

    public function test_botones_solo_aparecen_para_entrega_completa_en_vistas_de_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $completa = $this->entregaCompleta();
        $incompleta = $this->crearEntrega(Entrega::ESTADO_ENTREGADA);

        $listado = $this->get(route('entregas.index'))->assertOk();
        $listado->assertSee('href="'.route('entregas.comprobante', $completa).'"', false)
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertDontSee('href="'.route('entregas.comprobante', $incompleta).'"', false);

        $this->get(route('entregas.evidencia', $completa))
            ->assertOk()
            ->assertSee('Comprobante PDF')
            ->assertSee('target="_blank" rel="noopener"', false);
        $this->get(route('entregas.evidencia', $incompleta))
            ->assertOk()
            ->assertDontSee('Comprobante PDF');
    }

    private function entregaCompleta(bool $firmaCifrada = true, bool $sinFirma = false): Entrega
    {
        $entrega = $this->crearEntrega(Entrega::ESTADO_ENTREGADA, [
            'receptor_nombre' => 'María Ñandú',
            'firma_path' => $sinFirma ? null : 'entregas/firma/firma.png',
            'sin_firma' => $sinFirma,
            'motivo_sin_firma' => $sinFirma ? 'El receptor no pudo firmar por lesión' : null,
        ]);
        $fotoRuta = "entregas/{$entrega->id}/fotos/foto-1.png";
        Storage::disk('evidencias')->put($fotoRuta, $this->imagenPng(40, 30));
        EntregaFoto::create(['entrega_id' => $entrega->id, 'ruta' => $fotoRuta]);

        if (! $sinFirma) {
            $firma = $this->imagenPng(30, 15);
            Storage::disk('evidencias')->put($entrega->firma_path, $firmaCifrada ? Crypt::encryptString($firma) : $firma);
        }

        return $entrega->refresh()->load(['pedido.cliente', 'pedido.detalles', 'chofer', 'fotos']);
    }

    private function crearEntrega(string $estado, array $datos = []): Entrega
    {
        $cliente = Cliente::create(['nombre' => 'Cliente Cañón']);
        $pedido = Pedido::create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => '2026-11-01',
            'direccion' => 'Avenida Diseño 123',
            'estado' => 'completado',
            'total' => 100,
        ]);

        return Entrega::create([
            'pedido_id' => $pedido->id,
            'chofer_id' => User::factory()->chofer()->create(['name' => 'Chofer de prueba'])->id,
            'fecha_asignada' => '2026-11-20',
            'estado' => $estado,
            'estado_fecha_hora' => '2026-11-20 15:30:00',
            ...$datos,
        ]);
    }

    private function renderizar(Entrega $entrega): string
    {
        $this->actingAs(User::factory()->create());
        $entrega = app(EntregaRepositoryInterface::class)->findWithEvidencia($entrega->id);
        $servicio = app(EvidenciaEntregaService::class);
        $firma = $entrega->sin_firma ? null : $servicio->firmaComoDataUri($entrega->firma_path);
        $fotos = $entrega->fotos->sortBy('id')->take(3)->map(fn ($foto) => $servicio->fotoComoDataUri($foto->ruta));

        return view('reportes.comprobante-entrega', compact('entrega', 'firma', 'fotos'))->render();
    }

    private function imagenPng(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 50, 100, 150));
        ob_start();
        imagepng($imagen);
        $contenido = ob_get_clean();
        imagedestroy($imagen);

        return $contenido;
    }
}
