<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('clientes', 'nombre')->whereNull('deleted_at')->ignore($this->route('cliente')),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 120 caracteres.',
            'nombre.unique' => 'Ya existe un cliente con ese nombre.',
            'telefono.max' => 'El teléfono no puede superar los 30 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'telefono' => 'teléfono',
        ];
    }
}
