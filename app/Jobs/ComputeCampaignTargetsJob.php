<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignTargetingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ComputeCampaignTargetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign
    ) {}

    public function handle(CampaignTargetingService $targetingService): void
    {
        $targetingService->computeTargetsForCampaign($this->campaign);

        // If the channel includes 'email' or 'both', dispatch the email job here
        if (in_array($this->campaign->channel, ['email', 'both'], true)) {
            DispatchCampaignEmailsJob::dispatch($this->campaign);
        }
    }
}
