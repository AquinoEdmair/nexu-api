<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignUserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CampaignTargetingService
{
    /**
     * Compute and insert the target users into the campaign_user_status table.
     */
    public function computeTargetsForCampaign(Campaign $campaign): int
    {
        $query = User::query()->select('id');

        switch ($campaign->target_segment) {
            case 'all':
                // Query all active and inactive users (or just active, depending on rules)
                // Let's assume 'all' means all users
                break;
            case 'active':
                $query->active();
                break;
            case 'inactive':
                $query->where('status', '!=', 'active');
                break;
            case 'has_balance':
                $query->whereHas('wallet', function (Builder $q) {
                    $q->where('balance_total', '>', 0);
                });
                break;
            case 'no_deposit':
                $query->whereDoesntHave('transactions', function (Builder $q) {
                    $q->where('type', 'deposit')->where('status', 'confirmed');
                });
                break;
            case 'referred':
                $query->whereNotNull('referred_by');
                break;
            case 'custom':
                if (is_array($campaign->custom_target_query) && !empty($campaign->custom_target_query)) {
                    $query->whereIn('id', $campaign->custom_target_query);
                } else {
                    $query->whereRaw('1 = 0'); // Empty query if no users selected
                }
                break;
        }

        $userIds = $query->pluck('id');
        $insertedCount = 0;

        // Chunk insert
        foreach ($userIds->chunk(1000) as $chunk) {
            $insertData = $chunk->map(fn($userId) => [
                'id'          => \Illuminate\Support\Str::uuid()->toString(),
                'campaign_id' => $campaign->id,
                'user_id'     => $userId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ])->toArray();

            // Using insertOrIgnore to avoid duplicates if re-computed
            CampaignUserStatus::insertOrIgnore($insertData);
            $insertedCount += count($insertData);
        }

        return $insertedCount;
    }
}
