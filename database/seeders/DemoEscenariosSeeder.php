<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\User;
use App\Repositories\Contracts\EntregaRepositoryInterface;
use App\Repositories\Contracts\PedidoRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoEscenariosSeeder extends Seeder
{
    public const PREFIJO = '[DEMO] WOOD-86';

    private array $creados = [
        'clientes' => 0,
        'pedidos_octubre' => 0,
        'pedidos_noviembre' => 0,
        'pedidos_completados' => 0,
        'materiales' => 0,
        'entregas_hoy' => 0,
        'incidencias' => 0,
        'historial' => 0,
    ];

    public function run(PedidoRepositoryInterface $pedidos, EntregaRepositoryInterface $entregas): void
    {
        $this->autorizarEjecucion();

        $admin = User::query()->where('rol', User::ROL_ADMIN)->orderBy('id')->first();
        $chofer = User::query()->where('rol', User::ROL_CHOFER)->orderBy('id')->first();

        if (! $admin) {
            throw new RuntimeException('No existe ningún usuario con rol admin para registrar los escenarios de demostración.');
        }

        if (! $chofer) {
            throw new RuntimeException('No existe ningún usuario con rol chofer para asignar las entregas de demostración.');
        }

        $usuarioAnterior = Auth::user();
        Auth::login($admin);

        try {
            DB::transaction(function () use ($pedidos, $entregas, $chofer): void {
                $clientes = $this->crearClientes();
                [$materialBajo, $materialNormal] = $this->crearMateriales();

                $this->crearPedidosOctubre($pedidos, $entregas, $clientes, $materialNormal, $chofer);
                $this->crearPedidosCompletados($pedidos, $clientes[0], $materialNormal);
                $this->completarEstadosNoviembre($pedidos, $clientes, $materialNormal);
                $this->crearEntregasHoy($pedidos, $entregas, $clientes, $materialNormal, $chofer);
                $this->crearSituacionesNoviembre($pedidos, $entregas, $clientes, $materialNormal, $chofer);
                $this->crearHistorialIncidencias($pedidos, $entregas, $clientes[0], $materialNormal, $chofer);

                $materialBajo->refresh();
            });
        } finally {
            $usuarioAnterior ? Auth::login($usuarioAnterior) : Auth::logout();
        }

        $this->imprimirResumen();
    }

    private function autorizarEjecucion(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoEscenariosSeeder no puede ejecutarse en producción.');
        }

        if (! app()->environment('local') && ! $this->command?->confirm('El entorno no es local. ¿Deseas crear los datos de demostración?', false)) {
            throw new RuntimeException('Ejecución cancelada: se requiere confirmación explícita fuera del entorno local.');
        }
    }

    /** @return array<int, Cliente> */
    private function crearClientes(): array
    {
        return collect(['Casa Roble', 'Interior Norte', 'Muebles del Valle', 'Estudio Encino'])
            ->map(function (string $nombre, int $indice): Cliente {
                $nombre = self::PREFIJO.' '.$nombre;
                $cliente = Cliente::query()->where('nombre', $nombre)->first();

                if (! $cliente) {
                    $cliente = Cliente::create(['nombre' => $nombre, 'telefono' => '555-086-'.str_pad((string) ($indice + 1), 4, '0', STR_PAD_LEFT)]);
                    $this->creados['clientes']++;
                }

                return $cliente;
            })->all();
    }

    /** @return array{Material, Material} */
    private function crearMateriales(): array
    {
        $materiales = [
            ['nombre' => self::PREFIJO.' Bisagra bajo stock', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 2, 'stock_minimo' => 10],
            ['nombre' => self::PREFIJO.' Corredera stock normal', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 100, 'stock_minimo' => 10],
        ];

        $resultado = [];
        foreach ($materiales as $datos) {
            $material = Material::query()->where('nombre', $datos['nombre'])->first();
            if (! $material) {
                $material = Material::create($datos);
                $this->creados['materiales']++;
            }
            $resultado[] = $material;
        }

        return $resultado;
    }

    private function crearPedidosOctubre(PedidoRepositoryInterface $pedidos, EntregaRepositoryInterface $entregas, array $clientes, Material $material, User $chofer): void
    {
        $escenarios = [
            ['01', 9500, 'Mesa de comedor', 0],
            ['04', 10500, 'Librero modular', 1],
            ['07', 11000, 'Escritorio ejecutivo', 2],
            ['10', 12000, 'Clóset de recámara', 3],
            ['13', 12500, 'Cocina integral', 0],
            ['16', 10000, 'Centro de entretenimiento', 1],
            ['20', 11500, 'Credenza de nogal', 2],
            ['24', 10500, 'Juego de burós', 3],
            ['29', 12500, 'Mueble para baño', 0],
        ];

        foreach ($escenarios as $indice => [$dia, $total, $producto, $cliente]) {
            $creado = false;
            $pedido = $this->crearPedido($pedidos, "OCT-{$indice}", $clientes[$cliente], "2026-10-{$dia}", 'completado', $producto, 1, $total, $this->herraje($material), $creado);
            $this->asegurarHerraje($pedidos, $pedido, $material);
            $this->guardarEntrega($entregas, $pedido, $chofer, "OCT-{$indice}-ENTREGADA", "2026-10-{$dia}", Entrega::ESTADO_ENTREGADA, Carbon::parse("2026-10-{$dia} 17:00:00"));
            $this->creados['pedidos_octubre'] += (int) $creado;
        }

        $creado = false;
        $this->crearPedido($pedidos, 'OCT-CANCELADO', $clientes[1], '2026-10-30', 'cancelado', 'Pedido cancelado', 1, 15000, [], $creado);
        $this->creados['pedidos_octubre'] += (int) $creado;
    }

    private function crearPedidosCompletados(PedidoRepositoryInterface $pedidos, Cliente $cliente, Material $material): void
    {
        $creado = false;
        $this->crearPedido($pedidos, 'NOV-COMPLETADO-CON-HERRAJES', $cliente, '2026-11-08', 'completado', 'Cómoda con correderas', 1, 8500, [
            ['material_id' => $material->id, 'cantidad' => 4],
        ], $creado);
        $this->creados['pedidos_completados'] += (int) $creado;

        $creado = false;
        $this->crearPedido($pedidos, 'NOV-COMPLETADO-SIN-HERRAJES', $cliente, '2026-11-09', 'completado', 'Mesa sin herrajes', 1, 6200, [], $creado);
        $this->creados['pedidos_completados'] += (int) $creado;
    }

    private function completarEstadosNoviembre(PedidoRepositoryInterface $pedidos, array $clientes, Material $material): void
    {
        foreach (Pedido::ESTADOS as $indice => $estado) {
            if (Pedido::query()->whereBetween('fecha_pedido', ['2026-11-01', '2026-11-30'])->where('estado', $estado)->exists()) {
                continue;
            }

            $creado = false;
            $herrajes = $estado === 'completado' ? $this->herraje($material) : [];
            $pedido = $this->crearPedido($pedidos, 'NOV-ESTADO-'.strtoupper($estado), $clientes[$indice % count($clientes)], '2026-11-'.str_pad((string) (12 + $indice), 2, '0', STR_PAD_LEFT), $estado, 'Muestra de estado '.$estado, 1, 5000 + $indice * 500, $herrajes, $creado);
            if ($estado === 'completado') {
                $this->asegurarHerraje($pedidos, $pedido, $material);
            }
            $this->creados['pedidos_noviembre'] += (int) $creado;
        }
    }

    private function crearEntregasHoy(PedidoRepositoryInterface $pedidos, EntregaRepositoryInterface $entregas, array $clientes, Material $material, User $chofer): void
    {
        foreach (Entrega::ESTADOS as $indice => $estado) {
            $creadoPedido = false;
            $pedido = $this->crearPedido($pedidos, 'HOY-'.strtoupper($estado), $clientes[$indice], now()->toDateString(), 'completado', 'Entrega de hoy '.$estado, 1, 4500 + $indice * 500, $this->herraje($material), $creadoPedido);
            $this->asegurarHerraje($pedidos, $pedido, $material);
            if ($estado === Entrega::ESTADO_INCIDENCIA) {
                $this->guardarEntrega($entregas, $pedido, $chofer, 'HOY-INCIDENCIA-BASE', now()->subDay()->toDateString(), Entrega::ESTADO_PENDIENTE, now()->subDay()->setTime(16, 0));
            }
            $creada = $this->guardarEntrega($entregas, $pedido, $chofer, 'HOY-'.strtoupper($estado), now()->toDateString(), $estado, now()->setTime(9 + $indice, 0));
            $this->creados['entregas_hoy'] += (int) $creada;
        }
    }

    private function crearSituacionesNoviembre(PedidoRepositoryInterface $pedidos, EntregaRepositoryInterface $entregas, array $clientes, Material $material, User $chofer): void
    {
        foreach (['SIN-ATENDER', 'REASIGNADA', 'RESUELTA'] as $indice => $situacion) {
            $creadoPedido = false;
            $pedido = $this->crearPedido($pedidos, 'INC-'.$situacion, $clientes[$indice], '2026-11-05', 'completado', 'Pedido incidencia '.$situacion, 1, 7000 + $indice * 1000, $this->herraje($material), $creadoPedido);
            $this->asegurarHerraje($pedidos, $pedido, $material);
            $this->guardarEntrega($entregas, $pedido, $chofer, 'INC-'.$situacion.'-BASE', '2026-11-09', Entrega::ESTADO_PENDIENTE, Carbon::parse('2026-11-09 16:00:00'));
            $creada = $this->guardarEntrega($entregas, $pedido, $chofer, 'INC-'.$situacion.'-1', '2026-11-10', Entrega::ESTADO_INCIDENCIA, Carbon::parse(sprintf('2026-11-10 %02d:00:00', 8 + $indice)));
            $this->creados['incidencias'] += (int) $creada;

            if ($situacion !== 'SIN-ATENDER') {
                $estadoPosterior = $situacion === 'RESUELTA' ? Entrega::ESTADO_ENTREGADA : Entrega::ESTADO_PENDIENTE;
                $this->guardarEntrega($entregas, $pedido, $chofer, 'INC-'.$situacion.'-2', '2026-11-12', $estadoPosterior, Carbon::parse('2026-11-12 10:00:00'));
            }
        }
    }

    private function crearHistorialIncidencias(PedidoRepositoryInterface $pedidos, EntregaRepositoryInterface $entregas, Cliente $cliente, Material $material, User $chofer): void
    {
        $creadoPedido = false;
        $pedido = $this->crearPedido($pedidos, 'HISTORIAL-DOS-INCIDENCIAS', $cliente, '2026-11-14', 'completado', 'Pedido con historial', 1, 9300, $this->herraje($material), $creadoPedido);
        $this->asegurarHerraje($pedidos, $pedido, $material);
        $this->guardarEntrega($entregas, $pedido, $chofer, 'HISTORIAL-BASE', '2026-11-14', Entrega::ESTADO_PENDIENTE, Carbon::parse('2026-11-14 16:00:00'));

        foreach ([['HISTORIAL-1', '2026-11-15 09:00:00'], ['HISTORIAL-2', '2026-11-17 11:00:00']] as [$marca, $fecha]) {
            $creada = $this->guardarEntrega($entregas, $pedido, $chofer, $marca, substr($fecha, 0, 10), Entrega::ESTADO_INCIDENCIA, Carbon::parse($fecha));
            $this->creados['historial'] += (int) $creada;
        }
    }

    private function crearPedido(PedidoRepositoryInterface $pedidos, string $marca, Cliente $cliente, string $fecha, string $estado, string $producto, int $cantidad, float $precio, array $herrajes, bool &$creado): Pedido
    {
        $direccion = self::PREFIJO.' '.$marca;
        $existente = Pedido::query()->where('direccion', $direccion)->first();
        if ($existente) {
            $creado = false;

            return $existente;
        }

        $creado = true;

        return $pedidos->create([
            'cliente_id' => $cliente->id,
            'fecha_pedido' => $fecha,
            'estado' => $estado,
            'direccion' => $direccion,
            'detalles' => [['producto' => $producto, 'cantidad' => $cantidad, 'precio_unitario' => $precio]],
            'herrajes' => $herrajes,
        ]);
    }

    private function asegurarHerraje(PedidoRepositoryInterface $pedidos, Pedido $pedido, Material $material): void
    {
        if (! $pedido->herrajes()->exists()) {
            $pedidos->update($pedido->id, ['herrajes' => $this->herraje($material)]);
        }
    }

    private function herraje(Material $material): array
    {
        return [['material_id' => $material->id, 'cantidad' => 1]];
    }

    private function guardarEntrega(EntregaRepositoryInterface $entregas, Pedido $pedido, User $chofer, string $marca, string $fecha, string $estado, $fechaEstado): bool
    {
        $notas = self::PREFIJO.' '.$marca;
        $datos = [
            'pedido_id' => $pedido->id,
            'chofer_id' => $chofer->id,
            'fecha_asignada' => $fecha,
            'notas' => $notas,
            'estado' => $estado,
            'estado_fecha_hora' => $fechaEstado,
            'incidencia_motivo' => $estado === Entrega::ESTADO_INCIDENCIA ? 'Incidencia de demostración' : null,
            'accion_siguiente' => $estado === Entrega::ESTADO_INCIDENCIA ? 'Contactar y reprogramar' : null,
        ];
        $existente = Entrega::query()->where('notas', $notas)->first();

        if ($existente) {
            $entregas->update($existente->id, $datos);

            return false;
        }

        $entregas->create($datos);

        return true;
    }

    private function imprimirResumen(): void
    {
        $this->command?->info('Escenarios WOOD-86 listos. Registros nuevos:');
        foreach ($this->creados as $escenario => $cantidad) {
            $this->command?->info("- {$escenario}: {$cantidad}");
        }
        $this->command?->info('Identificador: prefijo "'.self::PREFIJO.'" en nombre, dirección o notas.');
    }
}
