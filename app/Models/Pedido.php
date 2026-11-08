<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use SoftDeletes;

    public const ESTADOS = ['pendiente', 'en_proceso', 'completado', 'cancelado'];

    public function scopeCompletadosSinEntrega(Builder $query): Builder
    {
        return $query->where('estado', 'completado')
            ->whereDoesntHave('entregas', fn ($entregas) => $entregas->whereIn('estado', [
                Entrega::ESTADO_PENDIENTE,
                Entrega::ESTADO_EN_CAMINO,
                Entrega::ESTADO_ENTREGADA,
            ]));
    }

    public function estaCompletadoSinEntrega(): bool
    {
        if ($this->trashed() || $this->estado !== 'completado') {
            return false;
        }

        $estadosConEntrega = [
            Entrega::ESTADO_PENDIENTE,
            Entrega::ESTADO_EN_CAMINO,
            Entrega::ESTADO_ENTREGADA,
        ];

        if ($this->relationLoaded('entregas')) {
            return ! $this->entregas->contains(fn (Entrega $entrega) => in_array($entrega->estado, $estadosConEntrega, true));
        }

        return ! $this->entregas()->whereIn('estado', $estadosConEntrega)->exists();
    }

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

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class);
    }

    public function entregaActual(): HasOne
    {
        return $this->hasOne(Entrega::class)->latestOfMany();
    }
}
