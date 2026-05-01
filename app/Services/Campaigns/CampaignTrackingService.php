<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use App\Models\CampaignUserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CampaignTrackingService
{
    public function markAsViewed(Campaign $campaign, User $user, ?array $metadata = null): void
    {
        DB::transaction(function () use ($campaign, $user, $metadata) {
            $status = CampaignUserStatus::firstOrCreate([
                'campaign_id' => $campaign->id,
                'user_id'     => $user->id,
            ]);

            $status->increment('view_count');
            
            if (is_null($status->viewed_at)) {
                $status->update(['viewed_at' => now()]);
            }

            CampaignEvent::create([
                'campaign_id' => $campaign->id,
                'user_id'     => $user->id,
                'event_type'  => 'viewed',
                'metadata'    => $metadata,
            ]);
        });
    }

    public function recordAction(Campaign $campaign, User $user, string $action, ?array $metadata = null): void
    {
        if (!in_array($action, ['accepted', 'rejected'], true)) {
            throw new \InvalidArgumentException("Invalid action: {$action}");
        }

        DB::transaction(function () use ($campaign, $user, $action, $metadata) {
            $status = CampaignUserStatus::firstOrCreate([
                'campaign_id' => $campaign->id,
                'user_id'     => $user->id,
            ]);

            $updateData = $action === 'accepted' 
                ? ['accepted_at' => now()] 
                : ['rejected_at' => now()];

            $status->update($updateData);

            CampaignEvent::create([
                'campaign_id' => $campaign->id,
                'user_id'     => $user->id,
                'event_type'  => $action,
                'metadata'    => $metadata,
            ]);
        });
    }
}
