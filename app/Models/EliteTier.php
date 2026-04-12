<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EliteTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'min_points',
        'max_points',
        'multiplier',
        'first_deposit_commission_rate',
        'recurring_commission_rate',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_points'                    => 'decimal:2',
            'max_points'                    => 'decimal:2',
            'multiplier'                    => 'decimal:2',
            'first_deposit_commission_rate' => 'decimal:4',
            'recurring_commission_rate'     => 'decimal:4',
            'is_active'                     => 'boolean',
            'sort_order'                    => 'integer',
        ];
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** @param Builder<EliteTier> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<EliteTier> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns true if the given points fall within this tier's range.
     */
    public function containsPoints(float|string $points): bool
    {
        $pts = (float) $points;

        if ($pts < (float) $this->min_points) {
            return false;
        }

        if ($this->max_points !== null && $pts > (float) $this->max_points) {
            return false;
        }

        return true;
    }

    /**
     * Progress percentage (0–100) toward the next tier's min_points.
     * Returns 100 when at the highest tier (max_points = null).
     */
    public function progressPct(float|string $currentPoints): int
    {
        if ($this->max_points === null) {
            return 100;
        }

        $pts   = (float) $currentPoints;
        $min   = (float) $this->min_points;
        $max   = (float) $this->max_points;
        $range = $max - $min;

        if ($range <= 0) {
            return 100;
        }

        return (int) min(100, round((($pts - $min) / $range) * 100));
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
