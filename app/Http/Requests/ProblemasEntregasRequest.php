<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class ProblemasEntregasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $hoy = Carbon::now(config('reportes.zona_horaria'));

        $this->merge([
            'desde' => $this->input('desde', $hoy->copy()->startOfMonth()->toDateString()),
            'hasta' => $this->input('hasta', $hoy->copy()->endOfMonth()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['desde', 'hasta'])) {
                    return;
                }

                $desde = Carbon::createFromFormat('!Y-m-d', $this->string('desde')->toString());
                $hasta = Carbon::createFromFormat('!Y-m-d', $this->string('hasta')->toString());

                if ($desde->diffInDays($hasta) > 365) {
                    $validator->errors()->add('hasta', 'El periodo no puede superar 366 días.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'desde.required' => 'La fecha desde es obligatoria.',
            'desde.date_format' => 'La fecha desde debe tener el formato AAAA-MM-DD.',
            'hasta.required' => 'La fecha hasta es obligatoria.',
            'hasta.date_format' => 'La fecha hasta debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('reportes.index');
    }
}
