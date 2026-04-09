<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BalanceSnapshot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'balance_available',
        'balance_in_operation',
        'balance_total',
        'snapshot_date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'balance_available'    => 'decimal:8',
            'balance_in_operation' => 'decimal:8',
            'balance_total'        => 'decimal:8',
            'snapshot_date'        => 'date:Y-m-d',
            'created_at'           => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
