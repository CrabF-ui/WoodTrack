<?php

namespace App\Http\Requests\Api;

use App\Models\Entrega;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarEntregasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', Rule::in(Entrega::ESTADOS)],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}
