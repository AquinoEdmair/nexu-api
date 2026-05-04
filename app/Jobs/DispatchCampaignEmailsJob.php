<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignUserStatus;
use App\Notifications\CampaignNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

final class DispatchCampaignEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign
    ) {}

    public function handle(): void
    {
        CampaignUserStatus::where('campaign_id', $this->campaign->id)
            ->with('user')
            ->chunk(500, function ($statuses) {
                $users = $statuses->map->user->filter();
                Notification::send($users, new CampaignNotification($this->campaign));
            });
    }
}
