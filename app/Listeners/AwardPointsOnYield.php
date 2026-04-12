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
        $yieldLogId = $event->yieldLog->id;

        // Query all yield transactions linked to this log via reference columns.
        $yieldTxs = Transaction::where('type', 'yield')
            ->where('status', 'confirmed')
            ->where('reference_type', 'yield_log')
            ->where('reference_id', $yieldLogId)
            ->cursor();

        foreach ($yieldTxs as $tx) {
            try {
                $this->referralService->awardPointsForYield($tx, $yieldLogId);
            } catch (Throwable $e) {
                Log::error('AwardPointsOnYield: failed for transaction', [
                    'yield_log_id'   => $yieldLogId,
                    'transaction_id' => $tx->id,
                    'user_id'        => $tx->user_id,
                    'error'          => $e->getMessage(),
                ]);
                // Continue processing remaining transactions.
            }
        }
    }
}
