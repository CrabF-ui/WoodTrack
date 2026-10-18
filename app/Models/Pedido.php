<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pedido extends Model
{
    use SoftDeletes;

    public const ESTADOS = ['pendiente', 'en_proceso', 'completado', 'cancelado'];

    protected $fillable = ['cliente_id', 'fecha_pedido', 'fecha_entrega', 'estado', 'total', 'direccion', 'alto', 'ancho', 'profundidad', 'material_principal', 'acabado_color', 'forma_pago', 'monto_anticipo', 'fecha_anticipo'];

    protected $casts = [
        'fecha_pedido' => 'date',
        'fecha_entrega' => 'date',
        'fecha_anticipo' => 'date',
        'total' => 'decimal:2',
        'monto_anticipo' => 'decimal:2',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function herrajes(): HasMany
    {
        return $this->hasMany(PedidoHerraje::class);
    }
}
