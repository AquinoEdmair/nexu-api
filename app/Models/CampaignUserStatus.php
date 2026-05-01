<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CampaignUserStatus extends Model
{
    use HasUuids;

    protected $table = 'campaign_user_status';

    protected $fillable = [
        'campaign_id',
        'user_id',
        'view_count',
        'viewed_at',
        'accepted_at',
        'rejected_at',
        'email_sent_at',
        'email_opened_at',
    ];

    protected function casts(): array
    {
        return [
            'view_count'      => 'integer',
            'viewed_at'       => 'datetime',
            'accepted_at'     => 'datetime',
            'rejected_at'     => 'datetime',
            'email_sent_at'   => 'datetime',
            'email_opened_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
