<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['dueno', 'admin', 'encargado_inventario', 'chofer'])->nullable()->change();
        });

        DB::table('users')->where('rol', 'dueno')->update(['rol' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['admin', 'encargado_inventario', 'chofer'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['dueno', 'admin', 'encargado_inventario', 'chofer'])->nullable()->change();
        });

        DB::table('users')->where('rol', 'admin')->update(['rol' => 'dueno']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('rol', ['dueno', 'encargado_inventario', 'chofer'])->nullable()->change();
        });
    }
};
