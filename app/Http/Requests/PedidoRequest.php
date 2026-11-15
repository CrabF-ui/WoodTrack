<?php

namespace App\Http\Requests;

use App\Models\Pedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $detalles = $this->input('detalles', []);
        $total = 0;

        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                if (! is_array($detalle)) {
                    continue;
                }

                $cantidad = $detalle['cantidad'] ?? null;
                $precio = $detalle['precio_unitario'] ?? null;

                if (filter_var($cantidad, FILTER_VALIDATE_INT) !== false
                    && (int) $cantidad >= 1
                    && is_numeric($precio)
                    && (float) $precio >= 0) {
                    $total += (int) $cantidad * (float) $precio;
                }
            }
        }

        $this->merge(['total' => $total]);

        if ($this->input('forma_pago') === 'Liquidado') {
            $this->merge([
                'monto_anticipo' => null,
                'fecha_anticipo' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->whereNull('deleted_at')],
            'fecha_pedido' => ['required', 'date'],
            'fecha_entrega' => ['required', 'date', 'after_or_equal:fecha_pedido'],
            'estado' => ['required', 'string', Rule::in(Pedido::ESTADOS)],
            'direccion' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.alto' => ['nullable', 'string', 'max:255'],
            'detalles.*.ancho' => ['nullable', 'string', 'max:255'],
            'detalles.*.profundidad' => ['nullable', 'string', 'max:255'],
            'detalles.*.material_principal' => ['nullable', 'string', 'max:255'],
            'detalles.*.acabado_color' => ['nullable', 'string', 'max:255'],
            'herrajes' => ['nullable', 'array'],
            'herrajes.*.material_id' => ['required_with:herrajes', 'exists:materials,id', 'distinct'],
            'herrajes.*.cantidad' => ['required_with:herrajes', 'numeric', 'min:0.01'],
            'forma_pago' => ['required', 'in:Liquidado,Anticipo'],
            'monto_anticipo' => ['required_if:forma_pago,Anticipo', 'nullable', 'numeric', 'min:0.01', 'lte:total'],
            'fecha_anticipo' => ['required_if:forma_pago,Anticipo', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio.',
            'cliente_id.exists' => 'Selecciona un cliente válido y activo.',
            'fecha_entrega.after_or_equal' => 'La fecha de entrega no puede ser anterior a la fecha del pedido.',
            'estado.in' => 'Selecciona un estado válido.',
            'detalles.*.producto.max' => 'El producto no puede superar los 255 caracteres.',
            'detalles.*.alto.max' => 'El alto del mueble no puede superar los 255 caracteres.',
            'detalles.*.ancho.max' => 'El ancho del mueble no puede superar los 255 caracteres.',
            'detalles.*.profundidad.max' => 'La profundidad del mueble no puede superar los 255 caracteres.',
            'detalles.*.material_principal.max' => 'El material principal no puede superar los 255 caracteres.',
            'detalles.*.acabado_color.max' => 'El acabado o color no puede superar los 255 caracteres.',
            'herrajes.*.material_id.distinct' => 'No repitas el mismo herraje.',
            'herrajes.*.cantidad.min' => 'La cantidad de herrajes debe ser mayor que cero.',
            'monto_anticipo.lte' => 'El anticipo no puede ser mayor que el total.',
            'fecha_anticipo.before_or_equal' => 'La fecha del anticipo no puede ser posterior a hoy.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_id' => 'cliente',
            'fecha_pedido' => 'fecha del pedido',
            'fecha_entrega' => 'fecha de entrega',
            'estado' => 'estado',
            'direccion' => 'dirección',
            'detalles' => 'productos del pedido',
            'detalles.*.producto' => 'producto',
            'detalles.*.cantidad' => 'cantidad del producto',
            'detalles.*.precio_unitario' => 'precio unitario',
            'detalles.*.alto' => 'alto del mueble',
            'detalles.*.ancho' => 'ancho del mueble',
            'detalles.*.profundidad' => 'profundidad del mueble',
            'detalles.*.material_principal' => 'material principal',
            'detalles.*.acabado_color' => 'acabado o color',
            'herrajes.*.material_id' => 'herraje',
            'herrajes.*.cantidad' => 'cantidad del herraje',
            'forma_pago' => 'forma de pago',
            'monto_anticipo' => 'monto del anticipo',
            'fecha_anticipo' => 'fecha del anticipo',
        ];
    }
}
