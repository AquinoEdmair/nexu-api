<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CommissionConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'value',
        'is_active',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value'     => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    /** @param Builder<CommissionConfig> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<CommissionConfig> $query */
    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /** @param Builder<CommissionConfig> $query */
    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<Admin, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
