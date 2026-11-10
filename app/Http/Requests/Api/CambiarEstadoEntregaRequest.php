<?php

namespace App\Http\Requests\Api;

use App\Models\Entrega;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([Entrega::ESTADO_PENDIENTE, Entrega::ESTADO_EN_CAMINO])],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser pendiente o en_camino.',
        ];
    }
}
