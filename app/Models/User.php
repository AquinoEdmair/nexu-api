<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'referral_code',
        'referred_by',
        'status',
        'blocked_reason',
        'blocked_at',
        'email_verified_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'phone_verified_at'  => 'datetime',
            'blocked_at'         => 'datetime',
            'password'           => 'hashed',
        ];
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** @param \Illuminate\Database\Eloquent\Builder<User> $query */
    public function scopeActive($query): void
    {
        $query->where('status', 'active');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<User> $query */
    public function scopeBlocked($query): void
    {
        $query->where('status', 'blocked');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<User> $query */
    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return HasOne<Wallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /** @return HasMany<User, $this> */
    public function referredUsers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /** @return HasOne<Referral, $this> */
    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    /** @return HasMany<Referral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /** @return HasMany<ElitePoint, $this> */
    public function elitePoints(): HasMany
    {
        return $this->hasMany(ElitePoint::class);
    }

    /** @return HasMany<WithdrawalRequest, $this> */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
