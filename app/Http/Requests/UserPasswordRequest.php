<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UserPasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::defaults()]];
    }

    public function messages(): array { return ['required' => 'El campo :attribute es obligatorio.', 'password.confirmed' => 'La confirmación de la contraseña no coincide.', 'password.min' => 'La contraseña debe tener al menos :min caracteres.']; }

    public function attributes(): array { return ['password' => 'contraseña']; }
}
