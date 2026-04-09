<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\YieldApplied;
use App\Notifications\AdminYieldCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyAdminOnYieldCompleted implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(YieldApplied $event): void
    {
        $admin = $event->yieldLog->appliedBy;

        if ($admin === null) {
            return;
        }

        $admin->notify(new AdminYieldCompletedNotification($event->yieldLog));
    }
}
