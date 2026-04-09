<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\YieldApplied;
use App\Models\YieldLogUser;
use App\Notifications\YieldAppliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyUsersOnYieldApplied implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(YieldApplied $event): void
    {
        YieldLogUser::with('user:id,name,email,phone')
            ->where('yield_log_id', $event->yieldLog->id)
            ->where('status', 'applied')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    /** @var YieldLogUser $row */
                    $row->user?->notify(new YieldAppliedNotification($row));
                }
            });
    }
}
