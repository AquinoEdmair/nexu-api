<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositConfirmed;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessReferralOnDeposit
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    /**
     * Apply referral commission to the referrer and award Elite points to the
     * depositor when a deposit is confirmed. Idempotent — safe to retry.
     */
    public function handle(DepositConfirmed $event): void
    {
        // 1. Commission to referrer (if user was referred) + points to referrer.
        try {
            $tx = $this->referralService->applyDepositCommission($event->transaction);

            if ($tx !== null) {
                Log::info('ProcessReferralOnDeposit: commission applied', [
                    'commission_tx_id' => $tx->id,
                    'deposit_id'       => $event->transaction->id,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('ProcessReferralOnDeposit: failed to apply commission', [
                'deposit_id' => $event->transaction->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // 2. Elite points to the depositor.
        try {
            $this->referralService->awardPointsForDeposit($event->transaction);
        } catch (Throwable $e) {
            Log::error('ProcessReferralOnDeposit: failed to award deposit points', [
                'deposit_id' => $event->transaction->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
