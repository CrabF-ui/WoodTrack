<?php

namespace App\Http\Requests\Api;

use App\Services\EvidenciaEntregaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntregarEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receptor_nombre' => ['required', 'string', 'min:2', 'max:120'],
            'fotos' => ['required', 'array', 'min:1', 'max:'.EvidenciaEntregaService::MAXIMO_FOTOS],
            'fotos.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'firma' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'motivo_sin_firma' => [
                'required_without:firma',
                Rule::prohibitedIf($this->hasFile('firma')),
                'string',
                'min:'.EvidenciaEntregaService::MOTIVO_MINIMO,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'receptor_nombre.required' => 'El nombre del receptor es obligatorio.',
            'receptor_nombre.string' => 'El nombre del receptor debe ser texto.',
            'receptor_nombre.min' => 'El nombre del receptor debe tener entre 2 y 120 caracteres.',
            'receptor_nombre.max' => 'El nombre del receptor debe tener entre 2 y 120 caracteres.',
            'fotos.required' => 'Debe registrar al menos una foto.',
            'fotos.array' => 'Las fotos deben enviarse como un arreglo.',
            'fotos.min' => 'Debe registrar al menos una foto.',
            'fotos.max' => 'No puede registrar más de 3 fotos.',
            'fotos.*.required' => 'Cada foto es obligatoria.',
            'fotos.*.image' => 'Cada foto debe ser una imagen.',
            'fotos.*.mimes' => 'Cada foto debe tener formato JPG o PNG.',
            'fotos.*.max' => 'Cada foto no debe superar 5 MB.',
            'firma.image' => 'La firma debe ser una imagen.',
            'firma.mimes' => 'La firma debe tener formato JPG o PNG.',
            'firma.max' => 'La firma no debe superar 5 MB.',
            'motivo_sin_firma.required_without' => 'Indique un motivo de al menos 10 caracteres cuando no haya firma.',
            'motivo_sin_firma.prohibited' => 'No puede registrar una firma y un motivo de falta de firma al mismo tiempo.',
            'motivo_sin_firma.string' => 'El motivo de falta de firma debe ser texto.',
            'motivo_sin_firma.min' => 'Indique un motivo de al menos 10 caracteres cuando no haya firma.',
        ];
    }

    public function attributes(): array
    {
        return [
            'receptor_nombre' => 'nombre del receptor',
            'fotos' => 'fotos',
            'fotos.*' => 'foto',
            'firma' => 'firma',
            'motivo_sin_firma' => 'motivo de falta de firma',
        ];
    }
}
