<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CAMPOS = ['alto', 'ancho', 'profundidad', 'material_principal', 'acabado_color'];

    public function up(): void
    {
        Schema::table('detalle_pedidos', function (Blueprint $table) {
            foreach (self::CAMPOS as $campo) {
                $table->string($campo, 255)->nullable();
            }
        });

        DB::table('pedidos')
            ->where(function ($query) {
                foreach (self::CAMPOS as $campo) {
                    $query->orWhereNotNull($campo);
                }
            })
            ->orderBy('id')
            ->get(['id', ...self::CAMPOS])
            ->each(function ($pedido): void {
                $detalleId = DB::table('detalle_pedidos')
                    ->where('pedido_id', $pedido->id)
                    ->orderBy('id')
                    ->value('id');

                if ($detalleId !== null) {
                    DB::table('detalle_pedidos')->where('id', $detalleId)->update(
                        collect(self::CAMPOS)->mapWithKeys(fn (string $campo) => [$campo => $pedido->{$campo}])->all(),
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::table('detalle_pedidos', function (Blueprint $table) {
            $table->dropColumn(self::CAMPOS);
        });
    }
};
