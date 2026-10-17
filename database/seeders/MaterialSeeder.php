<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            ['nombre' => 'Corredera telescópica 50 cm', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 48, 'stock_minimo' => 12],
            ['nombre' => 'Jaladera metálica negra', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 72, 'stock_minimo' => 20],
            ['nombre' => 'Bisagra cromada', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 96, 'stock_minimo' => 24],
            ['nombre' => 'Cerradura de embutir', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 24, 'stock_minimo' => 6],
            ['nombre' => 'Tornillo cabeza plana 3/4 pulgada', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 120, 'stock_minimo' => 30],
            ['nombre' => 'Amortiguador de cierre suave', 'tipo' => 'herraje', 'unidad_medida' => 'pieza', 'stock_actual' => 36, 'stock_minimo' => 10],
            ['nombre' => 'Tabla de pino 3/4 pulgada', 'tipo' => 'madera', 'unidad_medida' => 'pieza', 'stock_actual' => 32, 'stock_minimo' => 8],
            ['nombre' => 'Hoja de MDF 6 mm', 'tipo' => 'madera', 'unidad_medida' => 'pieza', 'stock_actual' => 18, 'stock_minimo' => 5],
            ['nombre' => 'Triplay 1/2 pulgada', 'tipo' => 'madera', 'unidad_medida' => 'pieza', 'stock_actual' => 22, 'stock_minimo' => 6],
            ['nombre' => 'Barniz poliuretano transparente', 'tipo' => 'acabado', 'unidad_medida' => 'cubeta', 'stock_actual' => 6, 'stock_minimo' => 2],
            ['nombre' => 'Tinte nogal americano', 'tipo' => 'acabado', 'unidad_medida' => 'litro', 'stock_actual' => 12, 'stock_minimo' => 3],
        ];

        foreach ($materials as $material) {
            Material::updateOrCreate(['nombre' => $material['nombre']], $material);
        }
    }
}
