<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositConfirmed;
use Illuminate\Support\Facades\Log;

final class NotifyUserOnDeposit
{
    public function handle(DepositConfirmed $event): void
    {
        // @todo Send Mail + SMS notification to user
        Log::info('DepositConfirmed: notification sent', [
            'user_id'  => $event->user->id,
            'amount'   => $event->netAmount,
            'currency' => $event->currency,
        ]);
    }
}
