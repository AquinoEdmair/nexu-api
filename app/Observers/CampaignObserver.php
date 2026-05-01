<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\ComputeCampaignTargetsJob;
use App\Models\Campaign;

final class CampaignObserver
{
    /**
     * Handle the Campaign "saved" event.
     * We use 'saved' to cover both creation and updates where is_active is toggled.
     */
    public function saved(Campaign $campaign): void
    {
        // If the campaign was just activated or it was already active but the targeting rules changed
        if ($campaign->isDirty('is_active') && $campaign->is_active) {
            ComputeCampaignTargetsJob::dispatch($campaign);
        } elseif ($campaign->is_active && ($campaign->isDirty('target_segment') || $campaign->isDirty('custom_target_query'))) {
            // Re-compute targets if rules changed while active
            ComputeCampaignTargetsJob::dispatch($campaign);
        }
    }
}
