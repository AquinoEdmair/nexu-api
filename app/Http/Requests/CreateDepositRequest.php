<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $minimum = (float) SystemSetting::get('minimum_deposit_amount', '0');

        return [
            'currency' => ['required', 'string', Rule::exists('deposit_currencies', 'symbol')->where('is_active', true)],
            'amount'   => ['required', 'numeric', 'max:999999999', $minimum > 0 ? "min:{$minimum}" : 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $minimum = (float) SystemSetting::get('minimum_deposit_amount', '0');

        return [
            'amount.min' => "El monto mínimo de depósito es \${$minimum} USD.",
        ];
    }
}
