<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrega_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entregas')->cascadeOnDelete();
            $table->string('ruta');
            $table->timestamps();

            $table->index('entrega_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_fotos');
    }
};
