<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Referral;
use App\Models\CommissionConfig;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserAuthService
{
    /**
     * @param  array{email: string, password: string} $credentials
     * @return array{user: User, token: string}
     * @throws ValidationException
     */
    public function login(array $credentials): array
    {
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status === 'blocked') {
            throw ValidationException::withMessages([
                'email' => [__('Your account has been blocked. Contact support.')],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta aún no ha sido verificada. Por favor, revisa tu correo electrónico para confirmarla.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null, referral_code?: string|null} $data
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = DB::transaction(function () use ($data): User {
            $referrer = $this->resolveReferrer($data['referral_code'] ?? null, $data['email']);

            $user = User::create([
                'name'          => $data['name'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'password'      => Hash::make($data['password']),
                'referral_code' => $this->generateUniqueReferralCode(),
                'referred_by'   => $referrer?->id,
                'status'        => 'pending',
            ]);

            Wallet::create([
                'user_id'              => $user->id,
                'balance_available'    => 0,
                'balance_in_operation' => 0,
                'balance_total'        => 0,
            ]);

            if ($referrer !== null) {
                $rate = $this->getReferralCommissionRate();
                Referral::create([
                    'referrer_id'     => $referrer->id,
                    'referred_id'     => $user->id,
                    'commission_rate' => $rate,
                    'total_earned'    => 0,
                ]);
            }

            return $user;
        });

        // Fires Laravel's built-in listener that calls sendEmailVerificationNotification()
        event(new Registered($user));

        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Revoke the current access token and issue a new one (refresh).
     *
     * @return array{user: User, token: string}
     */
    public function refresh(User $user): array
    {
        $user->currentAccessToken()->delete();
        $token = $user->createToken('api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * Send password reset link via email.
     * Always succeeds silently (anti-enumeration: never reveal whether email exists).
     */
    public function sendPasswordResetLink(string $email): void
    {
        Password::broker('users')->sendResetLink(['email' => $email]);
    }

    /**
     * Reset user password with token.
     *
     * @param  array{email: string, password: string, token: string} $data
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('users')->reset(
            [
                'email'                 => $data['email'],
                'password'              => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token'                 => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Resolve the referrer user by referral code.
     * Returns null for blank codes or self-referral attempts.
     */
    private function resolveReferrer(?string $referralCode, ?string $registrantEmail = null): ?User
    {
        if (blank($referralCode)) {
            return null;
        }

        $referrer = User::where('referral_code', strtoupper(trim($referralCode)))->first();

        // Prevent self-referral (same email address).
        if ($referrer !== null && $registrantEmail !== null
            && strtolower($referrer->email) === strtolower($registrantEmail)) {
            throw ValidationException::withMessages([
                'referral_code' => ['No puedes utilizar tu propio código de referido para registrarte.'],
            ]);
        }

        return $referrer;
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function getReferralCommissionRate(): string
    {
        $config = CommissionConfig::where('type', 'referral')
            ->where('is_active', true)
            ->latest()
            ->first();

        return $config?->value ?? '0.0500';
    }

    /**
     * Update user profile basic data.
     *
     * @param  User  $user
     * @param  array{name?: string, phone?: string|null} $data
     * @return User
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update(array_intersect_key($data, array_flip(['name', 'phone'])));

        return $user->fresh();
    }
}
