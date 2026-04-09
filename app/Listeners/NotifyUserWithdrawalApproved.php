<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalApproved;
use App\Notifications\WithdrawalApprovedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyUserWithdrawalApproved implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(WithdrawalApproved $event): void
    {
        $event->withdrawalRequest->user->notify(
            new WithdrawalApprovedNotification($event->withdrawalRequest)
        );
    }
}
