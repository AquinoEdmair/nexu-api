<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class ElitePoint extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'points',
        'transaction_id',
        'source_user_id',
        'description',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'points'     => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Only points that have NOT yet expired (expires_at >= today).
     *
     * @param Builder<ElitePoint> $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>=', Carbon::today()->toDateString());
    }

    /**
     * Only points that HAVE expired (expires_at < today).
     *
     * @param Builder<ElitePoint> $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<', Carbon::today()->toDateString());
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
