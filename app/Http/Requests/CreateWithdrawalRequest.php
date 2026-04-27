<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\WithdrawalCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount'              => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'currency'            => ['required', 'string', Rule::exists('withdrawal_currencies', 'symbol')->where('is_active', true)],
            'destination_address' => ['nullable', 'required_without:qr_image', 'string', 'min:20', 'max:255'],
            'qr_image'            => ['nullable', 'required_without:destination_address', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
