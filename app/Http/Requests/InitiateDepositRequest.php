<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CryptoCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InitiateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $activeSymbols = CryptoCurrency::active()->pluck('symbol')->toArray();

        return [
            'amount'   => ['required', 'numeric', 'min:10'],
            'currency' => ['required', 'string', Rule::in($activeSymbols)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'currency.in' => 'La moneda seleccionada no está disponible en este momento.',
        ];
    }
}
