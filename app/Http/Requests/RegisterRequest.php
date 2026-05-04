<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\TurnstileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'email'         => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:users,email'],
            'password'      => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone'         => ['nullable', 'string', 'max:20'],
            'referral_code' => [
                'nullable',
                'string',
                'max:10',
                function ($attribute, $value, $fail) {
                    $referrer = \App\Models\User::where('referral_code', strtoupper(trim($value)))->first();
                    if ($referrer && strtolower($referrer->email) === strtolower($this->email)) {
                        $fail('No puedes utilizar tu propio código de referido para registrarte.');
                    }
                },
            ],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique'       => 'Este correo electrónico ya está registrado.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}
