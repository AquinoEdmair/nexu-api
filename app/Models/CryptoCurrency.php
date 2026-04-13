<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CryptoCurrency extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'symbol',
        'now_payments_code',
        'network',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<CryptoCurrency> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<CryptoCurrency> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }
}
