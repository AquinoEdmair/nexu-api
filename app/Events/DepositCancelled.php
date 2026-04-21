<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DepositInvoice;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DepositCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly DepositInvoice $invoice,
        public readonly string $reason,
    ) {
    }
}
