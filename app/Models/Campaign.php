<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Campaign extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'description',
        'image_url',
        'type',
        'channel',
        'target_segment',
        'custom_target_query',
        'cta_text',
        'cta_url',
        'cta_type',
        'priority',
        'display_frequency',
        'start_at',
        'end_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'custom_target_query' => 'array',
            'start_at'            => 'datetime',
            'end_at'              => 'datetime',
            'is_active'           => 'boolean',
            'priority'            => 'integer',
        ];
    }

    /** @return HasMany<CampaignUserStatus, $this> */
    public function userStatuses(): HasMany
    {
        return $this->hasMany(CampaignUserStatus::class);
    }
}
