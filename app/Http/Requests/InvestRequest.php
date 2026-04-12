<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InvestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric'  => 'El monto debe ser un número.',
            'amount.min'      => 'El monto mínimo para invertir es $1.00.',
            'amount.max'      => 'El monto supera el límite permitido.',
        ];
    }
}
