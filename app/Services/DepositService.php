<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CryptoProviderInterface;
use App\Events\DepositConfirmed;
use App\Models\DepositInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DepositService
{
    public function __construct(
        private readonly CryptoProviderInterface $cryptoProvider,
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * Creates a deposit invoice via the crypto provider.
     *
     * @throws \RuntimeException if the provider call fails
     */
    public function initiateDeposit(User $user, float $amount, string $currency): DepositInvoice
    {
        $dto = $this->cryptoProvider->createInvoice($user->id, $amount, $currency);

        return DepositInvoice::create([
            'user_id'    => $user->id,
            'invoice_id' => $dto->invoiceId,
            'currency'   => $dto->currency,
            'network'    => $dto->network,
            'address'    => $dto->address,
            'qr_code_url' => $dto->qrCodeUrl,
            'status'     => 'awaiting_payment',
            'amount_expected' => $amount,
            'expires_at' => $dto->expiresAt,
        ]);
    }

    /**
     * Processes a confirmed deposit webhook payload.
     * Idempotent: skips if already processed.
     * All balance operations run inside DB::transaction with lockForUpdate.
     *
     * @param array{invoice_id: string, amount: string, currency: string, tx_hash: string} $payload
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function processWebhook(array $payload): void
    {
        $invoice = DepositInvoice::where('invoice_id', $payload['invoice_id'])->firstOrFail();

        // Idempotency: already processed
        if ($invoice->status === 'completed') {
            Log::info('DepositService: webhook already processed', ['invoice_id' => $payload['invoice_id']]);

            return;
        }

        // Idempotency: check external_tx_id uniqueness
        $txExists = Transaction::where('external_tx_id', $payload['tx_hash'])->exists();
        if ($txExists) {
            Log::warning('DepositService: duplicate tx_hash', ['tx_hash' => $payload['tx_hash']]);

            return;
        }

        $amount   = $payload['amount'];
        $currency = $payload['currency'];
        $txHash   = $payload['tx_hash'];
        $userId   = $invoice->user_id;

        // Sandbox workaround: if amount is 0, fallback to expected amount for testing
        if ((float)$amount <= 0 && config('services.crypto.nowpayments.sandbox', true)) {
            $amount = (string)$invoice->amount_expected;
            Log::info('DepositService: sandbox amount fallback', ['original' => $payload['amount'], 'fallback' => $amount]);
        }

        Log::info('DepositService: processing confirmed deposit', [
            'invoice_id' => $invoice->invoice_id,
            'amount'     => $amount,
            'user_id'    => $userId,
        ]);

        $commissionRate = $this->commissionService->getActiveRate('deposit');
        $rateDecimal    = bcdiv((string) $commissionRate, '100', 10);
        $feeAmount      = bcmul((string)$amount, $rateDecimal, 8);
        $netAmount      = bcsub((string)$amount, $feeAmount, 8);

        DB::transaction(function () use ($invoice, $userId, $amount, $feeAmount, $netAmount, $currency, $txHash, $commissionRate): void {
            Log::info('DepositService: starting DB transaction', ['user_id' => $userId]);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            // Create deposit transaction
            $depositTx = Transaction::create([
                'user_id'        => $userId,
                'type'           => 'deposit',
                'amount'         => $amount,
                'fee_amount'     => $feeAmount,
                'net_amount'     => $netAmount,
                'currency'       => $currency,
                'status'         => 'confirmed',
                'external_tx_id' => $txHash,
                'metadata'       => [
                    'invoice_id'      => $invoice->invoice_id,
                    'commission_rate' => $commissionRate,
                ],
            ]);

            // Create commission transaction if fee > 0
            if (bccomp($feeAmount, '0', 8) > 0) {
                Transaction::create([
                    'user_id'        => $userId,
                    'type'           => 'commission',
                    'amount'         => $feeAmount,
                    'fee_amount'     => '0.00000000',
                    'net_amount'     => $feeAmount,
                    'currency'       => $currency,
                    'status'         => 'confirmed',
                    'reference_type' => 'deposit',
                    'reference_id'   => $depositTx->id,
                ]);
            }

            // Update wallet balance
            $newInOperation = bcadd((string)$wallet->balance_in_operation, (string)$netAmount, 8);
            $newTotal       = bcadd((string)$wallet->balance_available, (string)$newInOperation, 8);

            $wallet->update([
                'balance_in_operation' => $newInOperation,
                'balance_total'        => $newTotal,
            ]);

            // Mark invoice as completed
            $invoice->update([
                'status'          => 'completed',
                'amount_received' => $amount,
                'tx_hash'         => $txHash,
                'transaction_id'  => $depositTx->id,
                'completed_at'    => now(),
            ]);
        });

        // Fire event outside transaction (for listeners/notifications)
        $invoice->refresh();
        $user = User::findOrFail($userId);
        DepositConfirmed::dispatch($user, $invoice->transaction, $netAmount, $currency);
    }

    /**
     * Returns active (non-expired, awaiting) invoices for a user.
     *
     * @return Collection<int, DepositInvoice>
     */
    public function getPendingInvoices(User $user): Collection
    {
        return DepositInvoice::where('user_id', $user->id)
            ->active()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Marks stale invoices as expired.
     * Called by ExpireStaleInvoices Job.
     */
    public function expireStaleInvoices(): int
    {
        return DepositInvoice::expired()
            ->update(['status' => 'expired']);
    }

    /**
     * Get all deposit invoices for the user (history).
     */
    public function getInvoiceHistory(User $user): Collection
    {
        return DepositInvoice::where('user_id', $user->id)
            ->latest()
            ->get();
    }

}
