<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class DetallePedido extends Model
{
    protected $fillable = ['pedido_id', 'producto', 'cantidad', 'precio_unitario', 'alto', 'ancho', 'profundidad', 'material_principal', 'acabado_color'];

    protected $casts = [
        'precio_unitario' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
