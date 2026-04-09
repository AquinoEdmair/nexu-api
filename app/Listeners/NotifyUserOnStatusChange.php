<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserStatusChanged;
use App\Notifications\UserStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyUserOnStatusChange implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(UserStatusChanged $event): void
    {
        $event->user->notify(
            new UserStatusChangedNotification($event->newStatus, $event->reason)
        );
    }
}
