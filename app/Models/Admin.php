<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class Admin extends Authenticatable implements FilamentUser
{
    use HasUuids;
    use Notifiable;

    /** Bcrypt cost factor used for all admin password hashing. */
    public const BCRYPT_ROUNDS = 12;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'password'                   => 'hashed',
            'two_factor_secret'          => 'encrypted',
            'two_factor_recovery_codes'  => 'encrypted:array',
            'two_factor_confirmed_at'    => 'datetime',
            'last_login_at'              => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function hasRecoveryCodes(): bool
    {
        return ! empty($this->two_factor_recovery_codes);
    }

    /** @return array<string> */
    public function recoveryCodes(): array
    {
        return $this->two_factor_recovery_codes ?? [];
    }

    public function scopeSuperAdmin(Builder $query): void
    {
        $query->where('role', 'super_admin');
    }

    public function scopeManager(Builder $query): void
    {
        $query->where('role', 'manager');
    }
}
