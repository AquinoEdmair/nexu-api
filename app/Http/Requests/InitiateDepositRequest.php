<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InitiateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount'   => ['required', 'numeric', 'min:10'],
            'currency' => ['required', 'string', 'in:USDT,BTC,ETH'],
        ];
    }
}
