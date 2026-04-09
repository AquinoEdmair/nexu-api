<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositConfirmed;
use App\Models\Referral;
use Illuminate\Support\Facades\Log;

final class ProcessReferralOnDeposit
{
    public function handle(DepositConfirmed $event): void
    {
        $referral = Referral::where('referred_id', $event->user->id)->first();

        if ($referral === null) {
            return;
        }

        // @todo Implement full referral commission via ReferralService
        // This listener should:
        // 1. Calculate commission = netAmount × referral.commission_rate
        // 2. Create Transaction(type=referral_commission) for referrer
        // 3. Credit referrer's wallet.balance_available
        // 4. Sum ElitePoints to referrer (1 pt / $1 USD)
        // 5. Update referral.total_earned

        Log::info('ProcessReferralOnDeposit: referral commission pending', [
            'referrer_id' => $referral->referrer_id,
            'referred_id' => $event->user->id,
            'net_amount'  => $event->netAmount,
        ]);
    }
}
