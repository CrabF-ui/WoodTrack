<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Administración WoodTrack', 'email' => 'admin@woodtrack.test', 'rol' => 'admin'],
            ['name' => 'Encargado Inventario', 'email' => 'inventario@woodtrack.test', 'rol' => 'encargado_inventario'],
            ['name' => 'Chofer Prueba', 'email' => 'chofer@woodtrack.test', 'rol' => 'chofer'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [...$user, 'password' => 'password123'],
            );
        }
    }
}
