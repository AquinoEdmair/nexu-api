<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\TurnstileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'email'         => ['required', 'string', 'lowercase', 'email', 'max:150'],
            'password'      => ['required', 'string', 'min:6'],
            'captcha_token' => ['required', 'string'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var TurnstileService $turnstile */
                $turnstile = app(TurnstileService::class);

                if (!$turnstile->verify((string) $this->input('captcha_token', ''), $this->ip())) {
                    $validator->errors()->add('captcha_token', 'Verificación de seguridad fallida. Recarga la página e intenta de nuevo.');
                }
            },
        ];
    }
}
