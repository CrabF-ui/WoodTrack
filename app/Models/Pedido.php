<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected function estadoLabel(): Attribute
    {
        return Attribute::get(fn (): string => str($this->estado)->replace('_', ' ')->title()->toString());
    }

    protected function estadoBadgeClass(): Attribute
    {
        return Attribute::get(fn (): string => match ($this->estado) {
            self::ESTADOS[0] => 'text-bg-warning',
            self::ESTADOS[1] => 'text-bg-info',
            self::ESTADOS[2] => 'text-bg-success',
            self::ESTADOS[3] => 'text-bg-danger',
            default => 'text-bg-secondary',
        });
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
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
