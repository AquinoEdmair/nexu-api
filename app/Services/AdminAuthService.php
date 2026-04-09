<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AdminAuthResultDTO;
use App\DTOs\TwoFactorSetupDTO;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PragmaRX\Google2FA\Google2FA;

final class AdminAuthService
{
    private const TWO_FA_MAX_ATTEMPTS = 3;
    private const TWO_FA_LOCK_TTL     = 900;  // 15 min in seconds
    private const PASSWORD_RESET_TTL  = 60;   // minutes

    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    /**
     * Attempt to log in an admin by email and password.
     * If 2FA is enabled, returns requiresTwoFactor = true without completing login.
     */
    public function attemptLogin(string $email, string $password, string $ip): AdminAuthResultDTO
    {
        $admin = Admin::where('email', $email)->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            activity()->withProperties(['ip' => $ip])
                ->log("Intento de login fallido para {$email} desde {$ip}");

            return AdminAuthResultDTO::failure('Credenciales inválidas.');
        }

        if ($admin->hasTwoFactorEnabled()) {
            session(['auth.pending_2fa' => $admin->id]);

            return AdminAuthResultDTO::success($admin, requiresTwoFactor: true);
        }

        Auth::login($admin);
        $admin->update(['last_login_at' => now(), 'last_login_ip' => $ip]);

        activity()->causedBy($admin)
            ->withProperties(['ip' => $ip])
            ->log("Admin {$admin->email} inició sesión desde {$ip}");

        return AdminAuthResultDTO::success($admin);
    }

    /**
     * Verify a TOTP code for 2FA login.
     *
     * @throws void — returns false instead of throwing on invalid code
     */
    public function verifyTwoFactor(Admin $admin, string $code, string $ip): bool
    {
        if ($this->isTwoFactorLocked($admin)) {
            return false;
        }

        $valid = $this->google2fa->verifyKey(
            $admin->two_factor_secret ?? '',
            $code,
        );

        if (! $valid) {
            $attempts = $this->incrementTwoFactorAttempts($admin);

            if ($attempts >= self::TWO_FA_MAX_ATTEMPTS) {
                activity()->causedBy($admin)
                    ->withProperties(['ip' => $ip])
                    ->log("Admin {$admin->email} bloqueado 15min por intentos 2FA desde {$ip}");
            }

            return false;
        }

        Cache::forget($this->twoFactorLockKey($admin->id));
        $admin->update(['last_login_at' => now(), 'last_login_ip' => $ip]);

        activity()->causedBy($admin)
            ->withProperties(['ip' => $ip])
            ->log("Admin {$admin->email} completó 2FA desde {$ip}");

        return true;
    }

    /**
     * Consume a recovery code for 2FA bypass.
     * The used code is removed from the stored list.
     */
    public function useRecoveryCode(Admin $admin, string $code, string $ip): bool
    {
        $codes = $admin->recoveryCodes();
        $key   = array_search($code, $codes, strict: true);

        if ($key === false) {
            return false;
        }

        unset($codes[$key]);
        $admin->update(['two_factor_recovery_codes' => array_values($codes)]);
        $admin->update(['last_login_at' => now(), 'last_login_ip' => $ip]);

        activity()->causedBy($admin)
            ->withProperties(['ip' => $ip])
            ->log("Admin {$admin->email} usó recovery code desde {$ip}");

        return true;
    }

    /**
     * Generate a new 2FA secret and return setup data (QR URL + raw secret).
     * Clears any previously confirmed 2FA state.
     */
    public function generateTwoFactorSecret(Admin $admin): TwoFactorSetupDTO
    {
        $secret = $this->google2fa->generateSecretKey();

        $admin->update([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => null,
        ]);

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $admin->email,
            $secret,
        );

        return new TwoFactorSetupDTO(qrCodeUrl: $qrCodeUrl, secretKey: $secret);
    }

    /**
     * Confirm 2FA setup by verifying a code and generating recovery codes.
     *
     * @return array<string> Recovery codes
     *
     * @throws InvalidArgumentException If the provided code is invalid
     */
    public function confirmTwoFactor(Admin $admin, string $code): array
    {
        $valid = $this->google2fa->verifyKey($admin->two_factor_secret ?? '', $code);

        if (! $valid) {
            throw new InvalidArgumentException('Código 2FA incorrecto.');
        }

        $codes = $this->generateRecoveryCodes();

        $admin->update([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => $codes,
        ]);

        activity()->causedBy($admin)
            ->log("Admin {$admin->email} activó 2FA");

        return $codes;
    }

    /**
     * Disable 2FA for an admin after verifying their password.
     *
     * @throws InvalidArgumentException If password is incorrect
     */
    public function disableTwoFactor(Admin $admin, string $password): void
    {
        if (! Hash::check($password, $admin->password)) {
            throw new InvalidArgumentException('Contraseña incorrecta.');
        }

        $admin->update([
            'two_factor_secret'         => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ]);

        activity()->causedBy($admin)
            ->log("Admin {$admin->email} desactivó 2FA");
    }

    /**
     * Regenerate recovery codes after verifying the admin's password.
     *
     * @return array<string> New recovery codes
     *
     * @throws InvalidArgumentException If password is incorrect
     */
    public function regenerateRecoveryCodes(Admin $admin, string $password): array
    {
        if (! Hash::check($password, $admin->password)) {
            throw new InvalidArgumentException('Contraseña incorrecta.');
        }

        $codes = $this->generateRecoveryCodes();
        $admin->update(['two_factor_recovery_codes' => $codes]);

        activity()->causedBy($admin)
            ->log("Admin {$admin->email} regeneró recovery codes");

        return $codes;
    }

    /**
     * Send a password reset email to the given address (anti-enumeration: silent if not found).
     */
    public function sendPasswordReset(string $email): void
    {
        $admin = Admin::where('email', $email)->first();

        if (! $admin) {
            return; // anti-enumeration
        }

        $token = Str::uuid()->toString();

        DB::table('admin_password_reset_tokens')->upsert(
            ['email' => $email, 'token' => Hash::make($token), 'created_at' => now()],
            ['email'],
            ['token', 'created_at'],
        );

        $admin->notify(new \App\Notifications\AdminPasswordResetNotification($token));

        activity()->withProperties(['ip' => request()->ip()])
            ->log("Password reset solicitado para {$email}");
    }

    /**
     * Reset an admin's password using a valid reset token.
     *
     * @throws InvalidArgumentException If the token is invalid or expired
     */
    public function resetPassword(string $email, string $token, string $newPassword, string $ip): void
    {
        $record = DB::table('admin_password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }

        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > self::PASSWORD_RESET_TTL) {
            DB::table('admin_password_reset_tokens')->where('email', $email)->delete();
            throw new InvalidArgumentException('Token inválido o expirado.');
        }

        if (! Hash::check($token, $record->token)) {
            throw new InvalidArgumentException('Token inválido o expirado.');
        }

        $admin = Admin::where('email', $email)->firstOrFail();
        $admin->update([
            'password' => Hash::make($newPassword, ['rounds' => Admin::BCRYPT_ROUNDS]),
        ]);

        DB::table('admin_password_reset_tokens')->where('email', $email)->delete();

        activity()->causedBy($admin)
            ->withProperties(['ip' => $ip])
            ->log("Admin {$admin->email} reseteó su contraseña desde {$ip}");
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @return array<string>
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        return array_map(
            fn (): string => Str::lower(Str::random(5)) . '-' . Str::lower(Str::random(5)),
            range(1, $count),
        );
    }

    private function twoFactorLockKey(string $adminId): string
    {
        return "2fa_lock:{$adminId}";
    }

    private function isTwoFactorLocked(Admin $admin): bool
    {
        return (int) Cache::get($this->twoFactorLockKey($admin->id), 0) >= self::TWO_FA_MAX_ATTEMPTS;
    }

    private function incrementTwoFactorAttempts(Admin $admin): int
    {
        $key      = $this->twoFactorLockKey($admin->id);
        $attempts = (int) Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, self::TWO_FA_LOCK_TTL);

        return $attempts;
    }
}
