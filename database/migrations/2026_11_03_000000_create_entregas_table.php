<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('chofer_id')->constrained('users')->restrictOnDelete();
            $table->date('fecha_asignada');
            $table->unsignedSmallInteger('orden')->nullable();
            $table->string('notas', 255)->nullable();
            $table->enum('estado', ['pendiente', 'en_camino', 'entregada', 'incidencia'])->default('pendiente');
            $table->dateTime('estado_fecha_hora')->nullable();
            $table->timestamps();

            $table->index(['chofer_id', 'fecha_asignada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
