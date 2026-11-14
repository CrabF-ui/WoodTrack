<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaterialRequest extends FormRequest
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
                'max:255',
                Rule::unique('materials', 'nombre')->whereNull('deleted_at')->ignore($this->route('material')),
            ],
            'tipo' => ['required', Rule::in(['madera', 'herraje', 'acabado'])],
            'unidad_medida' => ['required', Rule::in(['pieza', 'pie', 'pulgada', 'cm', 'mm', 'litro', 'cubeta'])],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stock_inicial' => [$this->isMethod('post') ? 'nullable' : 'prohibited', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $materialId = $this->route('material');
            if (! $materialId || ! $this->filled('tipo')) {
                return;
            }

            $material = Material::withCount([
                'movimientos' => fn ($query) => $query->withTrashed(),
                'pedidoHerrajes',
            ])->find($materialId);
            if ($material && $material->tipo !== $this->input('tipo') && ($material->movimientos_count > 0 || $material->pedido_herrajes_count > 0)) {
                $validator->errors()->add('tipo', 'El tipo no se puede cambiar porque el material tiene movimientos o pedidos asociados.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
            'nombre.unique' => 'Ya existe un material activo con ese nombre.',
            'tipo.in' => 'Selecciona un tipo de material válido.',
            'unidad_medida.in' => 'Selecciona una unidad de medida válida.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'min' => 'El campo :attribute no puede ser negativo.',
            'max' => 'El campo :attribute no puede superar 99,999,999.99.',
            'stock_inicial.prohibited' => 'El stock actual solo puede modificarse mediante movimientos de inventario.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'tipo' => 'tipo',
            'unidad_medida' => 'unidad de medida',
            'stock_minimo' => 'stock mínimo',
            'stock_inicial' => 'stock inicial',
        ];
    }
}
