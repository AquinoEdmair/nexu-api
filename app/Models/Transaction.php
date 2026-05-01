<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'amount',
        'fee_amount',
        'net_amount',
        'currency',
        'status',
        'external_tx_id',
        'metadata',
        'description',
        'reference_type',
        'reference_id',
        'available_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:8',
            'fee_amount'   => 'decimal:8',
            'net_amount'   => 'decimal:8',
            'metadata'     => 'array',
            'available_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (! $transaction->available_at && in_array($transaction->type, ['deposit', 'yield', 'referral_commission', 'commission'])) {
                $lock = \App\Models\WithdrawalLock::where('type', $transaction->type)
                    ->where('user_id', $transaction->user_id)
                    ->first() 
                    ?? \App\Models\WithdrawalLock::where('type', $transaction->type)
                    ->whereNull('user_id')
                    ->first();

                $days = $lock ? $lock->days : 0;
                $date = $transaction->created_at ? \Illuminate\Support\Carbon::parse($transaction->created_at) : now();
                $transaction->available_at = $date->addDays($days);
            }
        });
    }

    // ── Scopes (legacy, fixed-type) ──────────────────────────────────────────

    /** @param Builder<Transaction> $query */
    public function scopeDeposits(Builder $query): void
    {
        $query->where('type', 'deposit');
    }

    /** @param Builder<Transaction> $query */
    public function scopeWithdrawals(Builder $query): void
    {
        $query->where('type', 'withdrawal');
    }

    /** @param Builder<Transaction> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', 'confirmed');
    }

    // ── Scopes (parametric, for admin panel filters) ─────────────────────────

    /**
     * @param Builder<Transaction> $query
     * @param string|string[] $type
     */
    public function scopeByType(Builder $query, string|array $type): void
    {
        $query->whereIn('type', (array) $type);
    }

    /**
     * @param Builder<Transaction> $query
     * @param string|string[] $status
     */
    public function scopeByStatus(Builder $query, string|array $status): void
    {
        $query->whereIn('status', (array) $status);
    }

    /** @param Builder<Transaction> $query */
    public function scopeByCurrency(Builder $query, string $currency): void
    {
        $query->where('currency', $currency);
    }

    /** @param Builder<Transaction> $query */
    public function scopeBetweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    /** @param Builder<Transaction> $query */
    public function scopeAmountBetween(Builder $query, float $min, float $max): void
    {
        $query->whereBetween('net_amount', [$min, $max]);
    }

    /** @param Builder<Transaction> $query */
    public function scopeForUser(Builder $query, string $userId): void
    {
        $query->where('user_id', $userId);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return HasMany<ElitePoint, $this> */
    public function elitePoints(): HasMany
    {
        return $this->hasMany(ElitePoint::class);
    }

    /** @return BelongsTo<YieldLog, $this> */
    public function yieldLog(): BelongsTo
    {
        return $this->belongsTo(YieldLog::class, 'reference_id');
    }

    /** @return BelongsTo<WithdrawalRequest, $this> */
    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawalRequest::class, 'reference_id');
    }
}
