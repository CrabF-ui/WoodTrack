<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = ['nombre', 'tipo', 'unidad_medida', 'stock_actual', 'stock_minimo'];

    protected $casts = [
        'tipo' => 'string',
        'stock_actual' => 'decimal:2',
        'stock_minimo' => 'decimal:2',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function pedidoHerrajes(): HasMany
    {
        return $this->hasMany(PedidoHerraje::class);
    }
}
