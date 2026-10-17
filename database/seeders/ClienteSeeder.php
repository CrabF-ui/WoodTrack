<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = [
            ['nombre' => 'Maderería Los Pinos', 'telefono' => '555-010-1201'],
            ['nombre' => 'Constructora Horizonte', 'telefono' => '555-010-1202'],
            ['nombre' => 'Carpintería Roble y Cedro', 'telefono' => '555-010-1203'],
            ['nombre' => 'Diseño Interior Casa Clara', 'telefono' => '555-010-1204'],
            ['nombre' => 'Taller de Muebles La Estación', 'telefono' => '555-010-1205'],
        ];

        foreach ($clientes as $datos) {
            Cliente::withTrashed()->updateOrCreate(
                ['nombre' => $datos['nombre']],
                $datos
            );
        }
    }
}
