<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCommissionConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'type'        => ['required', 'string', 'in:deposit,referral'],
            'value'       => ['required', 'numeric', 'min:0.01', 'max:99.99'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.in'      => 'El tipo de comisión debe ser depósito o referido.',
            'value.min'    => 'El valor mínimo permitido es 0.01%.',
            'value.max'    => 'El valor máximo permitido es 99.99%.',
            'value.numeric' => 'El valor debe ser un número.',
        ];
    }
}
