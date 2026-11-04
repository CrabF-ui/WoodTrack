<?php

namespace App\Http\Requests;

use App\Models\Entrega;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $soloPendientes = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'pedido_id' => [
                $soloPendientes ? 'sometimes' : 'required',
                Rule::exists('pedidos', 'id')->whereNull('deleted_at'),
                function ($attribute, $value, $fail): void {
                    if (\App\Models\Pedido::whereKey($value)->where('estado', 'cancelado')->exists()) {
                        $fail('No se puede asignar una entrega a un pedido cancelado.');
                    }
                },
                function ($attribute, $value, $fail): void {
                    $activa = Entrega::where('pedido_id', $value)
                        ->whereIn('estado', Entrega::ESTADOS_ACTIVOS)
                        ->exists();

                    if ($activa) {
                        $fail('El pedido ya tiene una entrega activa.');
                    }
                },
            ],
            'chofer_id' => ['required', Rule::exists('users', 'id')->where('rol', 'chofer')],
            'fecha_asignada' => ['required', 'date', ...($soloPendientes ? [] : ['after_or_equal:today'])],
            'orden' => ['nullable', 'integer', 'min:1'],
            'notas' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'pedido_id.exists' => 'Selecciona un pedido asignable y activo.',
            'chofer_id.exists' => 'Selecciona un usuario con rol de chofer.',
            'fecha_asignada.date' => 'La fecha asignada debe ser una fecha válida.',
            'fecha_asignada.after_or_equal' => 'La fecha asignada no puede ser anterior a hoy.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden debe ser al menos 1.',
            'notas.max' => 'Las notas no pueden superar los 255 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'pedido_id' => 'pedido',
            'chofer_id' => 'chofer',
            'fecha_asignada' => 'fecha asignada',
            'orden' => 'orden',
            'notas' => 'notas',
        ];
    }
}
