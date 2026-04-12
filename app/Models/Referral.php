<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Referral extends Model
{
    use HasUuids;

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'commission_rate',
        'total_earned',
        'first_deposit_tx_id',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:4',
            'total_earned'    => 'decimal:8',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function firstDepositTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'first_deposit_tx_id');
    }
}
