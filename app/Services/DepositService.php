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
     * The user specifies how much they want to invest (net amount).
     * The system adds the commission on top so the user always receives 100% of their intended amount.
     *
     * Example: user wants $100, commission 5% → provider invoice = $105 → user receives $100.
     *
     * @throws \RuntimeException if the provider call fails
     */
    public function initiateDeposit(User $user, float $amount, string $currency): DepositInvoice
    {
        // Calculate gross amount (what the user must actually send)
        $commissionRate = $this->commissionService->getActiveRate('deposit');
        $rateDecimal    = bcdiv((string) $commissionRate, '100', 10);
        $feeAmount      = bcmul((string) $amount, $rateDecimal, 8);         // fee on top
        $amountCharged  = bcadd((string) $amount, $feeAmount, 8);           // gross = net + fee

        // Ask the crypto provider for the gross amount
        $dto = $this->cryptoProvider->createInvoice($user->id, (float) $amountCharged, $currency);

        $invoice = DepositInvoice::create([
            'user_id'         => $user->id,
            'invoice_id'      => $dto->invoiceId,
            'currency'        => $dto->currency,
            'network'         => $dto->network,
            'address'         => $dto->address,
            'qr_code_url'     => $dto->qrCodeUrl,
            'status'          => 'awaiting_payment',
            'amount_expected' => $amountCharged,  // USD gross
            'pay_amount'      => $dto->payAmount, // crypto amount to send
            'expires_at'      => $dto->expiresAt,
        ]);

        // Create a pending Transaction. amount = gross, net = intended net amount.
        $pendingTx = Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'amount'     => (string) $amountCharged,
            'fee_amount' => (string) $feeAmount,
            'net_amount' => (string) $amount,
            'currency'   => $dto->currency,
            'status'     => 'pending',
            'metadata'   => [
                'invoice_id'      => $dto->invoiceId,
                'commission_rate' => $commissionRate,
                'net_amount'      => (string) $amount,
            ],
        ]);

        $invoice->update(['transaction_id' => $pendingTx->id]);

        \App\Events\DepositInitiated::dispatch($user, $invoice, $amount, $currency);

        return $invoice;
    }

    /**
     * Processes a confirmed deposit webhook payload.
     * Idempotent: skips if already processed.
     * All balance operations run inside DB::transaction with lockForUpdate.
     *
     * @param array{invoice_id: string, amount: string, currency: string, tx_hash: string, actually_paid?: string, pay_currency?: string} $payload
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function processWebhook(array $payload): void
    {
        $result = DB::transaction(function () use ($payload): ?array {
            // 1. Lock invoice for update to prevent concurrent processing
            $invoice = DepositInvoice::where('invoice_id', $payload['invoice_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Idempotency: already processed (Inside lock)
            if ($invoice->status === 'completed') {
                Log::info('DepositService: webhook already processed', ['invoice_id' => $payload['invoice_id']]);
                return null;
            }

            // 3. Idempotency: check external_tx_id uniqueness (Inside lock)
            $txExists = Transaction::where('external_tx_id', $payload['tx_hash'])->exists();
            if ($txExists) {
                Log::warning('DepositService: duplicate tx_hash', ['tx_hash' => $payload['tx_hash']]);
                return null;
            }

            $amount   = $payload['amount'];
            $currency = $payload['currency'] ?? $invoice->currency;
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

            // Commission: the gross amount was already inflated at initiation.
            // net_amount = stored in pending tx metadata (what user wanted to invest)
            // fee_amount = gross - net
            $commissionRate = $this->commissionService->getActiveRate('deposit');
            $rateDecimal    = bcdiv((string) $commissionRate, '100', 10);

            // Recalculate from gross: net = gross / (1 + rate), fee = gross - net
            $divisor   = bcadd('1', $rateDecimal, 10);
            $netAmount = bcdiv((string)$amount, $divisor, 8);
            $feeAmount = bcsub((string)$amount, $netAmount, 8);

            Log::info('DepositService: locking wallet for update', ['user_id' => $userId]);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            // Update or create the deposit transaction.
            if ($invoice->transaction_id !== null) {
                /** @var Transaction $depositTx */
                $depositTx = Transaction::lockForUpdate()->findOrFail($invoice->transaction_id);
                $depositTx->update([
                    'amount'         => $amount,       // gross (what was actually received)
                    'fee_amount'     => $feeAmount,    // admin keeps this
                    'net_amount'     => $netAmount,    // user receives this
                    'currency'       => $currency,
                    'status'         => 'confirmed',
                    'external_tx_id' => $txHash,
                    'metadata'       => array_filter([
                        'invoice_id'      => $invoice->invoice_id,
                        'commission_rate' => $commissionRate,
                        'actually_paid'   => $payload['actually_paid'] ?? null,
                        'pay_currency'    => $payload['pay_currency'] ?? null,
                        'confirmed_by'    => $payload['confirmed_by'] ?? null,
                    ], fn($v) => $v !== null),
                ]);
            } else {
                $depositTx = Transaction::create([
                    'user_id'        => $userId,
                    'type'           => 'deposit',
                    'amount'         => $amount,
                    'fee_amount'     => $feeAmount,
                    'net_amount'     => $netAmount,
                    'currency'       => $currency,
                    'status'         => 'confirmed',
                    'external_tx_id' => $txHash,
                    'metadata'       => array_filter([
                        'invoice_id'      => $invoice->invoice_id,
                        'commission_rate' => $commissionRate,
                        'actually_paid'   => $payload['actually_paid'] ?? null,
                        'pay_currency'    => $payload['pay_currency'] ?? null,
                        'confirmed_by'    => $payload['confirmed_by'] ?? null,
                    ], fn($v) => $v !== null),
                ]);
            }

            // Credit user wallet with NET amount only (100% of their intended investment)
            $newInOperation = bcadd((string)$wallet->balance_in_operation, (string)$netAmount, 8);

            $wallet->update([
                'balance_in_operation' => $newInOperation,
                'balance_total'        => $newInOperation,
            ]);

            // Mark invoice as completed
            $invoice->update([
                'status'          => 'completed',
                'amount_received' => $amount,
                'tx_hash'         => $txHash,
                'transaction_id'  => $depositTx->id,
                'completed_at'    => now(),
            ]);

            return [
                'userId'         => $userId,
                'transaction'    => $depositTx,
                'netAmount'      => $netAmount,
                'feeAmount'      => $feeAmount,
                'commissionRate' => $commissionRate,
                'currency'       => $currency,
            ];
        });

        // 4. Fire events and record ledger entry outside transaction
        if ($result !== null) {
            try {
                $user = User::findOrFail($result['userId']);
                DepositConfirmed::dispatch($user, $result['transaction'], $result['netAmount'], $result['currency']);

                // Record commission to admin ledger (only if fee > 0)
                if (bccomp((string)$result['feeAmount'], '0', 8) > 0) {
                    $this->commissionService->recordToLedger(
                        sourceType:  'deposit',
                        sourceId:    $result['transaction']->id,
                        userId:      $result['userId'],
                        amount:      (float) $result['feeAmount'],
                        rate:        $result['commissionRate'],
                        description: "Comisión de depósito — {$result['currency']}",
                    );
                }
            } catch (\Throwable $e) {
                // Log the error but don't stop the process — the deposit is already saved in DB
                \Illuminate\Support\Facades\Log::error('DepositService: Notification or ledger failed after confirmation', [
                    'userId' => $result['userId'],
                    'error'  => $e->getMessage()
                ]);
            }
        }
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

    /**
     * Manually confirms a pending deposit invoice as if the webhook had arrived.
     * Uses amount_expected as the credited amount.
     * Records admin ID in transaction metadata for audit trail.
     *
     * @throws \RuntimeException if invoice is not awaiting_payment
     */
    public function confirmManually(DepositInvoice $invoice, int|string $adminId): void
    {
        if ($invoice->status !== 'awaiting_payment') {
            throw new \RuntimeException("Invoice {$invoice->invoice_id} no está pendiente de pago (status: {$invoice->status}).");
        }

        $this->processWebhook([
            'invoice_id'    => $invoice->invoice_id,
            'status'        => 'finished',
            'amount'        => (string) $invoice->amount_expected,
            'currency'      => 'USD',
            'tx_hash'       => 'manual-' . $adminId . '-' . time(),
            'actually_paid' => (string) ($invoice->pay_amount ?? $invoice->amount_expected),
            'pay_currency'  => $invoice->currency,
            'confirmed_by'  => (string) $adminId,
        ]);

        activity()
            ->causedBy(\App\Models\Admin::find($adminId))
            ->performedOn($invoice)
            ->withProperties(['admin_id' => $adminId, 'amount' => $invoice->amount_expected])
            ->log('Depósito confirmado manualmente');
    }

    /**
     * Cancels a pending deposit invoice without crediting the user.
     * Records admin ID in activity log.
     *
     * @throws \RuntimeException if invoice is not awaiting_payment
     */
    public function cancelInvoice(DepositInvoice $invoice, int|string $adminId, string $reason): void
    {
        if ($invoice->status !== 'awaiting_payment') {
            throw new \RuntimeException("Invoice {$invoice->invoice_id} no está pendiente de pago (status: {$invoice->status}).");
        }

        DB::transaction(function () use ($invoice, $adminId, $reason): void {
            // Cancel the pending transaction if it exists
            if ($invoice->transaction_id !== null) {
                Transaction::where('id', $invoice->transaction_id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected', 'metadata->cancel_reason' => $reason]);
            }

            $invoice->update([
                'status'       => 'failed',
                'completed_at' => now(),
            ]);
        });

        // Notify user about cancellation outside of transaction
        try {
            $invoice->load('user')->user->notify(new \App\Notifications\DepositCancelledNotification($invoice, $reason));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DepositService: Notification failed after cancellation', [
                'invoiceId' => $invoice->invoice_id,
                'error'     => $e->getMessage()
            ]);
        }

        activity()
            ->causedBy(\App\Models\Admin::find($adminId))
            ->performedOn($invoice)
            ->withProperties(['admin_id' => $adminId, 'reason' => $reason])
            ->log('Depósito cancelado manualmente');
    }

}
