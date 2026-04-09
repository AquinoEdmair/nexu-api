<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class YieldLogUser extends Model
{
    use HasUuids;

    protected $table = 'yield_log_users';

    protected $fillable = [
        'yield_log_id',
        'user_id',
        'balance_before',
        'balance_after',
        'amount_applied',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'balance_before' => 'decimal:8',
            'balance_after'  => 'decimal:8',
            'amount_applied' => 'decimal:8',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /** @return BelongsTo<YieldLog, $this> */
    public function yieldLog(): BelongsTo
    {
        return $this->belongsTo(YieldLog::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
