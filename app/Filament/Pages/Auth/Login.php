<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Services\AdminAuthService;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Login extends \Filament\Pages\Auth\Login
{
    /**
     * @throws ValidationException
     */
    public function authenticate(): ?LoginResponse
    {
        $ip  = request()->ip() ?? '0.0.0.0';
        $key = "admin-login:{$ip}";

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError('data.email', "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.");

            return null;
        }

        $data = $this->form->getState();

        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $result = app(AdminAuthService::class)->attemptLogin($email, $password, $ip);

        if (! $result->success) {
            RateLimiter::hit($key, 60);

            $this->addError('data.email', $result->error ?? 'Credenciales inválidas.');

            return null;
        }

        if ($result->requiresTwoFactor) {
            RateLimiter::clear($key);

            $this->redirect('/admin/two-factor-challenge');

            return null;
        }

        RateLimiter::clear($key);

        return app(LoginResponse::class);
    }
}
