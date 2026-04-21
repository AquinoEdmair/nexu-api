<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class WithdrawalRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WithdrawalRequest $request,
        public readonly User              $user,
    ) {}
}
