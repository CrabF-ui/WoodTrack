<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo', ['madera', 'herraje', 'acabado']);
            $table->string('unidad_medida');
            $table->decimal('stock_actual', 10, 2)->unsigned()->default(0);
            $table->decimal('stock_minimo', 10, 2)->unsigned()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
