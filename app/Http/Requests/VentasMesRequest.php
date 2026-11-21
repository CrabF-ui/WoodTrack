<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class VentasMesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mes' => $this->input('mes', Carbon::now(config('reportes.zona_horaria'))->format('Y-m')),
        ]);
    }

    public function rules(): array
    {
        return [
            'mes' => ['required', 'date_format:Y-m'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('mes')) {
                    return;
                }

                $mes = Carbon::createFromFormat('!Y-m', $this->string('mes')->toString(), config('reportes.zona_horaria'));
                $mesActual = Carbon::now(config('reportes.zona_horaria'))->startOfMonth();

                if ($mes->isAfter($mesActual)) {
                    $validator->errors()->add('mes', 'El mes no puede ser posterior al mes actual.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'mes.required' => 'El mes es obligatorio.',
            'mes.date_format' => 'El mes debe tener el formato AAAA-MM.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('reportes.index');
    }
}
