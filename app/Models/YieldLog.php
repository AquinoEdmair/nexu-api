<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class YieldLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'applied_by',
        'type',
        'value',
        'scope',
        'negative_policy',
        'specific_user_id',
        'status',
        'users_count',
        'total_applied',
        'description',
        'error_message',
        'applied_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:8',
            'total_applied' => 'decimal:8',
            'applied_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<Admin, $this> */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'applied_by');
    }

    /** @return HasMany<YieldLogUser, $this> */
    public function yieldLogUsers(): HasMany
    {
        return $this->hasMany(YieldLogUser::class);
    }
}
