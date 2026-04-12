<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WithdrawalRequest extends Model
{
    /** @use HasFactory<\Database\Factories\WithdrawalRequestFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'amount',
        'fee_amount',
        'net_amount',
        'commission_rate',
        'currency',
        'destination_address',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'tx_hash',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:8',
            'fee_amount'      => 'decimal:8',
            'net_amount'      => 'decimal:8',
            'commission_rate' => 'decimal:4',
            'reviewed_at'     => 'datetime',
        ];
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** @param \Illuminate\Database\Eloquent\Builder<WithdrawalRequest> $query */
    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<WithdrawalRequest> $query */
    public function scopeApproved($query): void
    {
        $query->where('status', 'approved');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<WithdrawalRequest> $query */
    public function scopeCompleted($query): void
    {
        $query->where('status', 'completed');
    }

    /** @param \Illuminate\Database\Eloquent\Builder<WithdrawalRequest> $query */
    public function scopeRejected($query): void
    {
        $query->where('status', 'rejected');
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
