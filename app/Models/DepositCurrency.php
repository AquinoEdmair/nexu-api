<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DepositCurrency extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'network',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<DepositAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(DepositAddress::class, 'currency_id');
    }

    /** @return HasMany<DepositAddress, $this> */
    public function activeAddresses(): HasMany
    {
        return $this->hasMany(DepositAddress::class, 'currency_id')->where('is_active', true);
    }
}
