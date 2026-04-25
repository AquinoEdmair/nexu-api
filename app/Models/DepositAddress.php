<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

final class DepositAddress extends Model
{
    use HasUuids;

    protected $fillable = [
        'currency_id',
        'address',
        'qr_image_path',
        'label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getQrImageUrlAttribute(): ?string
    {
        return $this->qr_image_path
            ? Storage::disk('public')->url($this->qr_image_path)
            : null;
    }

    /** @return BelongsTo<DepositCurrency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(DepositCurrency::class, 'currency_id');
    }

    /** @return HasMany<DepositRequest, $this> */
    public function depositRequests(): HasMany
    {
        return $this->hasMany(DepositRequest::class);
    }
}
