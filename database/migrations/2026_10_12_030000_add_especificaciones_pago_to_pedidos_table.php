<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('alto')->nullable();
            $table->string('ancho')->nullable();
            $table->string('profundidad')->nullable();
            $table->string('material_principal')->nullable();
            $table->string('acabado_color')->nullable();
            $table->enum('forma_pago', ['Liquidado', 'Anticipo'])->default('Liquidado');
            $table->decimal('monto_anticipo', 10, 2)->unsigned()->nullable();
            $table->date('fecha_anticipo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'alto',
                'ancho',
                'profundidad',
                'material_principal',
                'acabado_color',
                'forma_pago',
                'monto_anticipo',
                'fecha_anticipo',
            ]);
        });
    }
};
