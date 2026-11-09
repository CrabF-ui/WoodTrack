<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->string('receptor_nombre', 120)->nullable();
            $table->string('firma_path')->nullable();
            $table->boolean('sin_firma')->default(false);
            $table->text('motivo_sin_firma')->nullable();
            $table->text('incidencia_motivo')->nullable();
            $table->text('accion_siguiente')->nullable();
            $table->boolean('regresa_a_stock')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropColumn([
                'receptor_nombre',
                'firma_path',
                'sin_firma',
                'motivo_sin_firma',
                'incidencia_motivo',
                'accion_siguiente',
                'regresa_a_stock',
            ]);
        });
    }
};
