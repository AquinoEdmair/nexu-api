<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositConfirmed;
use App\Notifications\DepositConfirmedNotification;

final class NotifyUserOnDeposit
{
    public function handle(DepositConfirmed $event): void
    {
        $event->user->notify(new DepositConfirmedNotification($event->transaction));
    }
}
