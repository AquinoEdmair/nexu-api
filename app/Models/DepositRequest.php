<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

final class DepositRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'deposit_address_id',
        'currency',
        'network',
        'address',
        'qr_image_path',
        'amount_expected',
        'tx_hash',
        'status',
        'client_confirmed_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_expected'     => 'decimal:8',
            'client_confirmed_at' => 'datetime',
            'reviewed_at'         => 'datetime',
        ];
    }

    public function getQrImageUrlAttribute(): ?string
    {
        return $this->qr_image_path
            ? Storage::disk('public')->url($this->qr_image_path)
            : null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<DepositAddress, $this> */
    public function depositAddress(): BelongsTo
    {
        return $this->belongsTo(DepositAddress::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
