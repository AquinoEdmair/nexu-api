<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'currency'            => ['required', 'string', 'in:USDT,BTC,ETH'],
            'destination_address' => ['required', 'string', 'min:20', 'max:255'],
        ];
    }
}
