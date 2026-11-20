<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $table): void {
            $table->text('receptor_nombre')->nullable()->change();
        });

        DB::table('entregas')
            ->whereNotNull('receptor_nombre')
            ->orderBy('id')
            ->chunkById(100, function ($entregas): void {
                foreach ($entregas as $entrega) {
                    try {
                        Crypt::decryptString($entrega->receptor_nombre);
                    } catch (DecryptException) {
                        DB::table('entregas')
                            ->where('id', $entrega->id)
                            ->update(['receptor_nombre' => Crypt::encryptString($entrega->receptor_nombre)]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('entregas')
            ->whereNotNull('receptor_nombre')
            ->orderBy('id')
            ->chunkById(100, function ($entregas): void {
                foreach ($entregas as $entrega) {
                    try {
                        $nombre = Crypt::decryptString($entrega->receptor_nombre);
                    } catch (DecryptException) {
                        continue;
                    }

                    DB::table('entregas')
                        ->where('id', $entrega->id)
                        ->update(['receptor_nombre' => $nombre]);
                }
            });

        Schema::table('entregas', function (Blueprint $table): void {
            $table->string('receptor_nombre', 120)->nullable()->change();
        });
    }
};
