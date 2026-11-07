<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->route('usuario'))],
            'rol' => ['required', Rule::in([User::ROL_ADMIN, User::ROL_ENCARGADO_INVENTARIO, User::ROL_CHOFER])],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'El campo :attribute es obligatorio.', 'email.unique' => 'Ya existe un usuario con ese correo.', 'rol.in' => 'El rol seleccionado no es válido.'];
    }

    public function attributes(): array { return ['name' => 'nombre', 'email' => 'correo', 'rol' => 'rol']; }
}
