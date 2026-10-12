<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pedido extends Model
{
    use SoftDeletes;

    protected $fillable = ['cliente_id', 'fecha_pedido', 'fecha_entrega', 'estado', 'total', 'direccion'];

    protected $casts = [
        'fecha_pedido' => 'date',
        'fecha_entrega' => 'date',
        'total' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }
}