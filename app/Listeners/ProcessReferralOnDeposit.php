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
     * Apply referral commission and Elite points when a deposit is confirmed.
     * Idempotent — safe to retry.
     */
    public function handle(DepositConfirmed $event): void
    {
        try {
            $tx = $this->referralService->applyDepositCommission($event->transaction);

            if ($tx !== null) {
                Log::info('ProcessReferralOnDeposit: commission applied', [
                    'commission_tx_id' => $tx->id,
                    'deposit_id'       => $event->transaction->id,
                ]);
            }
        } catch (Throwable $e) {
            // Log but do not rethrow — a referral failure must never block
            // the main deposit confirmation flow.
            Log::error('ProcessReferralOnDeposit: failed to apply commission', [
                'deposit_id' => $event->transaction->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
