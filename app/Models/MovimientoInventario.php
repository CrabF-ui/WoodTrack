<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovimientoInventario extends Model
{
    use SoftDeletes;

    protected $fillable = ['material_id', 'tipo', 'cantidad', 'motivo', 'registrado_por', 'pedido_id', 'fecha'];

    protected $casts = [
        'tipo' => 'string',
        'cantidad' => 'decimal:2',
        'fecha' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
