<?php

namespace App\Http\Requests\Api;

use App\Services\EvidenciaEntregaService;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:'.EvidenciaEntregaService::MOTIVO_MINIMO, 'max:65535'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo es obligatorio.',
            'motivo.string' => 'El motivo debe ser texto.',
            'motivo.min' => 'El motivo de la incidencia debe tener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no debe exceder los 65535 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'motivo' => 'motivo',
        ];
    }
}
