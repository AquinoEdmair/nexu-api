<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositCancelled;
use App\Notifications\DepositCancelledNotification;

final class NotifyUserOnDepositCancelled
{
    public function handle(DepositCancelled $event): void
    {
        $event->user->notify(new DepositCancelledNotification($event->invoice, $event->reason));
    }
}
