<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntregaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pedido = $this->pedido;
        $cliente = $pedido?->cliente;

        return [
            'id' => $this->id,
            'fecha_asignada' => $this->fecha_asignada?->format('Y-m-d'),
            'orden' => $this->orden,
            'estado' => $this->estado,
            'estado_fecha_hora' => $this->estado_fecha_hora?->toIso8601String(),
            'notas' => $this->notas,
            'pedido' => [
                'id' => $pedido?->id,
                'cliente' => $cliente?->nombre,
                'telefono' => $cliente?->telefono,
                'direccion' => $pedido?->direccion,
                'fecha_entrega' => $pedido?->fecha_entrega?->format('Y-m-d'),
                'productos' => $pedido?->detalles->map(fn ($detalle) => [
                    'producto' => $detalle->producto,
                    'cantidad' => $detalle->cantidad,
                ])->values() ?? [],
            ],
        ];
    }
}
