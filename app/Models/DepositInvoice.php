<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DepositInvoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'currency',
        'network',
        'address',
        'qr_code_url',
        'status',
        'amount_expected',
        'pay_amount',
        'amount_received',
        'tx_hash',
        'transaction_id',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_expected' => 'decimal:8',
            'pay_amount'      => 'decimal:8',
            'amount_received' => 'decimal:8',
            'expires_at'      => 'datetime',
            'completed_at'    => 'datetime',
        ];
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** @param Builder<DepositInvoice> $query */
    public function scopeAwaiting(Builder $query): void
    {
        $query->where('status', 'awaiting_payment');
    }

    /** @param Builder<DepositInvoice> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'awaiting_payment')
            ->where('expires_at', '>', now());
    }

    /** @param Builder<DepositInvoice> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', 'awaiting_payment')
            ->where('expires_at', '<=', now());
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
