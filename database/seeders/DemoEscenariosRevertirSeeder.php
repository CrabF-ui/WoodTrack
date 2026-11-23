<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoEscenariosRevertirSeeder extends Seeder
{
    public function run(): void
    {
        $this->autorizarEjecucion();

        $resumen = DB::transaction(function (): array {
            $prefijo = DemoEscenariosSeeder::PREFIJO.'%';
            $pedidoIds = DB::table('pedidos')->where('direccion', 'like', $prefijo)->pluck('id');
            $materialIds = DB::table('materials')->where('nombre', 'like', $prefijo)->pluck('id');
            $clienteIds = DB::table('clientes')->where('nombre', 'like', $prefijo)->pluck('id');
            $entregaIds = DB::table('entregas')
                ->where(function ($query) use ($pedidoIds, $prefijo): void {
                    $query->where('notas', 'like', $prefijo)->orWhereIn('pedido_id', $pedidoIds);
                })->pluck('id');
            $movimientos = DB::table('movimiento_inventarios')
                ->where(function ($query) use ($pedidoIds, $materialIds): void {
                    $query->whereIn('pedido_id', $pedidoIds)->orWhereIn('material_id', $materialIds);
                })->get();

            foreach ($movimientos->groupBy('material_id') as $materialId => $movimientosMaterial) {
                $ajuste = $movimientosMaterial->sum(
                    fn ($movimiento): float => $movimiento->tipo === 'salida'
                        ? (float) $movimiento->cantidad
                        : -((float) $movimiento->cantidad),
                );

                if ($ajuste > 0) {
                    DB::table('materials')->where('id', $materialId)->increment('stock_actual', $ajuste);
                } elseif ($ajuste < 0) {
                    DB::table('materials')->where('id', $materialId)->decrement('stock_actual', abs($ajuste));
                }
            }

            $resumen = [
                'entregas' => $entregaIds->count(),
                'movimientos' => $movimientos->count(),
                'pedidos' => $pedidoIds->count(),
                'clientes' => $clienteIds->count(),
                'materiales' => $materialIds->count(),
            ];

            DB::table('entrega_fotos')->whereIn('entrega_id', $entregaIds)->delete();
            DB::table('entregas')->whereIn('id', $entregaIds)->delete();
            DB::table('movimiento_inventarios')->whereIn('id', $movimientos->pluck('id'))->delete();
            DB::table('pedido_herrajes')->whereIn('pedido_id', $pedidoIds)->delete();
            DB::table('detalle_pedidos')->whereIn('pedido_id', $pedidoIds)->delete();
            DB::table('pedidos')->whereIn('id', $pedidoIds)->delete();
            DB::table('clientes')->whereIn('id', $clienteIds)->delete();
            DB::table('materials')->whereIn('id', $materialIds)->delete();

            return $resumen;
        });

        $this->command?->info('Datos de demostración WOOD-86 eliminados:');
        foreach ($resumen as $tipo => $cantidad) {
            $this->command?->info("- {$tipo}: {$cantidad}");
        }
        $this->command?->info('El stock afectado por movimientos de pedidos demo fue restaurado antes de eliminarlos.');
    }

    private function autorizarEjecucion(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoEscenariosRevertirSeeder no puede ejecutarse en producción.');
        }

        if (! app()->environment('local') && ! $this->command?->confirm('El entorno no es local. ¿Deseas eliminar los datos de demostración?', false)) {
            throw new RuntimeException('Ejecución cancelada: se requiere confirmación explícita fuera del entorno local.');
        }
    }
}
