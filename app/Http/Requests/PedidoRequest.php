<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'fecha_pedido' => ['required', 'date'],
            'fecha_entrega' => ['required', 'date', 'after_or_equal:fecha_pedido'],
            'estado' => ['required', 'string'],
            'direccion' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto' => ['required', 'string'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'alto' => ['nullable', 'string', 'max:255'],
            'ancho' => ['nullable', 'string', 'max:255'],
            'profundidad' => ['nullable', 'string', 'max:255'],
            'material_principal' => ['nullable', 'string', 'max:255'],
            'acabado_color' => ['nullable', 'string', 'max:255'],
            'herrajes' => ['nullable', 'array'],
            'herrajes.*.material_id' => ['required_with:herrajes', 'exists:materials,id'],
            'herrajes.*.cantidad' => ['required_with:herrajes', 'numeric', 'min:0.01'],
            'total' => ['required', 'numeric', 'min:0'],
            'forma_pago' => ['required', 'in:Liquidado,Anticipo'],
            'monto_anticipo' => ['required_if:forma_pago,Anticipo', 'nullable', 'numeric', 'min:0.01', 'lte:total'],
            'fecha_anticipo' => ['required_if:forma_pago,Anticipo', 'nullable', 'date'],
        ];
    }
}
