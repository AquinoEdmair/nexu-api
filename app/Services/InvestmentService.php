<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientBalanceException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class InvestmentService
{
    /**
     * Move funds from balance_available to balance_in_operation.
     *
     * This represents the user "investing" their available balance so it
     * participates in yield distributions. The operation is atomic and
     * audited as a transaction of type 'investment'.
     *
     * @throws InsufficientBalanceException
     * @throws \RuntimeException if balance invariant is violated
     */
    public function invest(User $user, float $amount): Transaction
    {
        return DB::transaction(function () use ($user, $amount): Transaction {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $amountStr   = number_format($amount, 8, '.', '');
            $available   = (string) $wallet->balance_available;
            $inOperation = (string) $wallet->balance_in_operation;

            // Guard: saldo disponible insuficiente
            if (bccomp($available, $amountStr, 8) < 0) {
                throw new InsufficientBalanceException(
                    __('Saldo disponible insuficiente para realizar la inversión.')
                );
            }

            $newAvailable   = bcsub($available, $amountStr, 8);
            $newInOperation = bcadd($inOperation, $amountStr, 8);
            $newTotal       = bcadd($newAvailable, $newInOperation, 8);

            // Invariant check: total must equal available + in_operation
            $expectedTotal = bcadd($newAvailable, $newInOperation, 8);
            if (bccomp($newTotal, $expectedTotal, 8) !== 0) {
                throw new \RuntimeException('Balance invariant violation during investment.');
            }

            $wallet->update([
                'balance_available'    => $newAvailable,
                'balance_in_operation' => $newInOperation,
                'balance_total'        => $newTotal,
            ]);

            return Transaction::create([
                'user_id'    => $user->id,
                'type'       => 'investment',
                'amount'     => $amountStr,
                'fee_amount' => '0.00000000',
                'net_amount' => $amountStr,
                'currency'   => 'USD',
                'status'     => 'confirmed',
                'metadata'   => [
                    'balance_available_before'    => $available,
                    'balance_in_operation_before' => $inOperation,
                ],
            ]);
        });
    }
}
