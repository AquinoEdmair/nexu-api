<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class CampaignDeliveryService
{
    /**
     * Get active campaigns for the given user that they haven't completely accepted or rejected.
     * Evaluates display_frequency logic.
     *
     * @return Collection<int, Campaign>
     */
    public function getActiveCampaignsForUser(User $user): Collection
    {
        return Campaign::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            // Must have a status record mapped to this user (segmentation passed)
            ->whereHas('userStatuses', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    // If they accepted or rejected it, it's done.
                    ->whereNull('accepted_at')
                    ->whereNull('rejected_at')
                    // Check frequency
                    ->where(function ($q) {
                        // If 'once', they must not have viewed it yet
                        $q->whereHas('campaign', function ($campaignQuery) {
                            $campaignQuery->where('display_frequency', 'once');
                        })->whereNull('viewed_at')
                        // If 'until_accepted' or 'always', they can view it multiple times 
                        // (until accepted/rejected handles the null check above)
                        ->orWhereHas('campaign', function ($campaignQuery) {
                            $campaignQuery->whereIn('display_frequency', ['until_accepted', 'always']);
                        });
                    });
            })
            ->orderByDesc('priority')
            ->get();
    }
}
