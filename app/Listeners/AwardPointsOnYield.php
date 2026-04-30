<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\YieldApplied;
use App\Models\Transaction;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AwardPointsOnYield
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    /**
     * Award Elite points to every user who received a yield in this log.
     * Runs after the YieldApplied event — all yield transactions are already
     * committed at this point.
     */
    public function handle(YieldApplied $event): void
    {
        // Yields no longer generate elite points. Logic removed.
    }
}
