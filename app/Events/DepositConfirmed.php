<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DepositConfirmed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User        $user,
        public readonly Transaction $transaction,
        public readonly string      $netAmount,
        public readonly string      $currency,
    ) {}
}
