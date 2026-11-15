<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Entrega extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_CAMINO = 'en_camino';

    public const ESTADO_ENTREGADA = 'entregada';

    public const ESTADO_INCIDENCIA = 'incidencia';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_CAMINO,
        self::ESTADO_ENTREGADA,
        self::ESTADO_INCIDENCIA,
    ];

    public const ESTADOS_ACTIVOS = [self::ESTADO_PENDIENTE, self::ESTADO_EN_CAMINO];

    protected $fillable = [
        'pedido_id',
        'chofer_id',
        'fecha_asignada',
        'orden',
        'notas',
        'estado',
        'estado_fecha_hora',
        'receptor_nombre',
        'firma_path',
        'sin_firma',
        'motivo_sin_firma',
        'incidencia_motivo',
        'accion_siguiente',
        'regresa_a_stock',
    ];

    protected $casts = [
        'fecha_asignada' => 'date',
        'estado_fecha_hora' => 'datetime',
        'orden' => 'integer',
        'sin_firma' => 'boolean',
        'regresa_a_stock' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Entrega $entrega): void {
            $entrega->estado ??= self::ESTADO_PENDIENTE;
            $entrega->estado_fecha_hora ??= now();
        });
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class)->withTrashed();
    }

    public function chofer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chofer_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(EntregaFoto::class);
    }

    public function cambiarEstado(string $estado): void
    {
        if (! in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException("Estado de entrega inválido: {$estado}");
        }

        $this->update([
            'estado' => $estado,
            'estado_fecha_hora' => now(),
        ]);
    }
}
