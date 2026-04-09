<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalRejected;
use App\Notifications\WithdrawalRejectedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyUserWithdrawalRejected implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(WithdrawalRejected $event): void
    {
        $event->withdrawalRequest->user->notify(
            new WithdrawalRejectedNotification($event->withdrawalRequest)
        );
    }
}
