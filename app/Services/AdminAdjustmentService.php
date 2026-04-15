<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RecalculateEliteTierJob;
use App\Models\ElitePoint;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class AdminAdjustmentService
{
    /**
     * Apply a signed delta to the user's balance_in_operation.
     *
     * @param  string $field  Must be 'balance_in_operation' (balance_available no longer exists).
     * @param  string $delta  Signed decimal string. Positive = credit, negative = debit.
     * @param  string $reason Human-readable reason shown in transaction description.
     * @param  string $adminId  ID of the admin performing the action (stored in metadata).
     *
     * @throws \InvalidArgumentException When the resulting balance would go negative.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When user has no wallet.
     */
    public function adjustWallet(
        User   $user,
        string $field,
        string $delta,
        string $reason,
        string $adminId,
    ): Transaction {
        // Redirect any legacy 'balance_available' adjustments to balance_in_operation
        if ($field === 'balance_available') {
            $field = 'balance_in_operation';
        }

        return DB::transaction(function () use ($user, $field, $delta, $reason, $adminId): Transaction {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $previous = (string) $wallet->balance_in_operation;
            $newValue = bcadd($previous, $delta, 8);

            if (bccomp($newValue, '0', 8) < 0) {
                throw new \InvalidArgumentException(
                    "El ajuste dejaría balance_in_operation en negativo ({$newValue})."
                );
            }

            $wallet->update([
                'balance_in_operation' => $newValue,
                'balance_total'        => $newValue,
            ]);

            return Transaction::create([
                'user_id'     => $user->id,
                'wallet_id'   => $wallet->id,
                'type'        => 'admin_adjustment',
                'amount'      => $delta,
                'fee_amount'  => '0.00000000',
                'net_amount'  => $delta,
                'currency'    => 'USD',
                'status'      => 'confirmed',
                'description' => $reason,
                'metadata'    => [
                    'admin_id'       => $adminId,
                    'field_adjusted' => $field,
                    'previous_value' => $previous,
                    'new_value'      => $newValue,
                ],
            ]);
        });
    }

    /**
     * Apply a signed delta to a user's elite points.
     *
     * @param  string $delta   Signed decimal string. Positive = award, negative = deduct.
     * @param  string $reason  Stored in description as "admin:{reason}".
     * @param  string $adminId Stored in metadata for audit.
     */
    public function adjustPoints(
        User   $user,
        string $delta,
        string $reason,
        string $adminId,
    ): ElitePoint {
        $point = ElitePoint::create([
            'user_id'        => $user->id,
            'points'         => $delta,
            'transaction_id' => null,
            'description'    => "admin:{$adminId}:{$reason}",
        ]);

        RecalculateEliteTierJob::dispatch($user->id);

        return $point;
    }
}
