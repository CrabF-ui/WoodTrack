<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimientoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('fecha')) {
            $this->merge(['fecha' => now()->toDateString()]);
        }
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', Rule::exists('materials', 'id')->whereNull('deleted_at')],
            'tipo' => ['required', Rule::in(['entrada', 'salida'])],
            'cantidad' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'fecha' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'material_id.exists' => 'Selecciona un material válido y activo.',
            'tipo.in' => 'Selecciona un tipo de movimiento válido.',
            'cantidad.min' => 'La cantidad debe ser mayor que cero.',
            'cantidad.max' => 'La cantidad no puede superar 99,999,999.99.',
            'fecha.before_or_equal' => 'La fecha del movimiento no puede ser posterior a hoy.',
        ];
    }

    public function attributes(): array
    {
        return [
            'material_id' => 'material',
            'tipo' => 'tipo de movimiento',
            'cantidad' => 'cantidad',
            'motivo' => 'motivo',
            'fecha' => 'fecha',
        ];
    }
}
