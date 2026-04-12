<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AdminCommissionLedger extends Model
{
    use HasUuids;

    protected $table = 'admin_commission_ledger';

    protected $fillable = [
        'source_type',
        'source_id',
        'user_id',
        'amount',
        'commission_rate',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:8',
            'commission_rate' => 'decimal:4',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /** @param Builder<AdminCommissionLedger> $query */
    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('source_type', $type);
    }

    /** @param Builder<AdminCommissionLedger> $query */
    public function scopeForPeriod(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
