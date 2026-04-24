<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateWithdrawalDTO;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalRejected;
use App\Events\WithdrawalRequested;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalCompletedNotification;
use App\Notifications\WithdrawalCreatedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class WithdrawalService
{
    public function __construct(
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * Create a withdrawal request and immediately reserve funds from balance_in_operation.
     *
     * Commission logic:
     *   - User requests to withdraw $amount (gross)
     *   - fee_amount = amount × commission_rate
     *   - net_amount = amount - fee_amount  ← what user actually receives
     *   - The full $amount is reserved from the wallet (fee included)
     *
     * @throws InsufficientBalanceException
     * @throws \Throwable
     */
    public function create(CreateWithdrawalDTO $dto, User $user): WithdrawalRequest
    {
        $result = DB::transaction(function () use ($dto, $user): WithdrawalRequest {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $dto->amount > (float) $wallet->balance_in_operation) {
                throw new InsufficientBalanceException(
                    (float) $dto->amount,
                    (float) $wallet->balance_in_operation
                );
            }

            // Calculate commission
            $commissionRate = $this->commissionService->getActiveRate('withdrawal');
            $rateDecimal    = bcdiv((string) $commissionRate, '100', 10);
            $feeAmount      = bcmul((string) $dto->amount, $rateDecimal, 8);
            $netAmount      = bcsub((string) $dto->amount, $feeAmount, 8);

            // Reserve the full requested amount (fee is part of it)
            $newInOperation = bcsub((string) $wallet->balance_in_operation, (string) $dto->amount, 8);

            $wallet->update([
                'balance_in_operation' => $newInOperation,
                'balance_total'        => $newInOperation,
            ]);

            $request = WithdrawalRequest::create([
                'user_id'             => $user->id,
                'amount'              => $dto->amount,
                'fee_amount'          => $feeAmount,
                'net_amount'          => $netAmount,
                'commission_rate'     => $commissionRate,
                'currency'            => $dto->currency,
                'destination_address' => $dto->destinationAddress,
                'qr_image_path'       => $dto->qrImagePath,
                'status'              => 'pending',
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($request)
                ->log("Usuario {$user->email} solicitó retiro por \${$dto->amount} (comisión: {$commissionRate}%, neto: \${$netAmount})");

            return $request;
        });

        $user->notify(new WithdrawalCreatedNotification($result));

        event(new WithdrawalRequested($result, $user));

        return $result;
    }

    /**
     * Approve a pending withdrawal request.
     *
     * @throws InvalidStatusTransitionException
     * @throws \Throwable
     */
    public function approve(WithdrawalRequest $request, Admin $admin): WithdrawalRequest
    {
        $result = DB::transaction(function () use ($request, $admin): WithdrawalRequest {
            $fresh = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            $this->assertStatus($fresh, 'pending');

            $fresh->update([
                'status'      => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            return $fresh;
        });

        activity()
            ->causedBy($admin)
            ->performedOn($result)
            ->log("Admin {$admin->name} aprobó retiro {$result->id}");

        WithdrawalApproved::dispatch($result->load('user'));

        return $result;
    }

    /**
     * Reject a pending or approved withdrawal request and release funds back.
     * Full amount is returned (fee is not charged on rejected withdrawals).
     *
     * @throws InvalidStatusTransitionException
     * @throws \Throwable
     */
    public function reject(WithdrawalRequest $request, string $reason, Admin $admin): WithdrawalRequest
    {
        $result = DB::transaction(function () use ($request, $reason, $admin): WithdrawalRequest {
            $fresh = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            $this->assertStatusIn($fresh, ['pending', 'approved']);

            $fresh->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'reviewed_by'      => $admin->id,
                'reviewed_at'      => now(),
            ]);

            // Return the FULL amount (no fee charged on rejected withdrawals)
            $this->releaseToWallet($fresh);
            $this->createWithdrawalTransaction($fresh, 'rejected');

            return $fresh;
        });

        activity()
            ->causedBy($admin)
            ->performedOn($result)
            ->log("Admin {$admin->name} rechazó retiro {$result->id}: {$reason}");

        WithdrawalRejected::dispatch($result->load('user'));

        return $result;
    }

    /**
     * Mark an approved withdrawal request as completed.
     * Records the commission fee to the admin ledger.
     *
     * @throws InvalidStatusTransitionException
     * @throws \Throwable
     */
    public function complete(WithdrawalRequest $request, string $txHash, Admin $admin): WithdrawalRequest
    {
        $result = DB::transaction(function () use ($request, $txHash): WithdrawalRequest {
            $fresh = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            $this->assertStatus($fresh, 'approved');

            $fresh->update([
                'status'  => 'completed',
                'tx_hash' => $txHash,
            ]);

            // Transaction records the NET amount sent to user
            $this->createWithdrawalTransaction($fresh, 'confirmed');

            return $fresh;
        });

        activity()
            ->causedBy($admin)
            ->performedOn($result)
            ->log("Admin {$admin->name} completó retiro {$result->id}, tx_hash: {$txHash}");

        // Record commission to admin ledger OUTSIDE the transaction
        $feeAmount = (float) $result->fee_amount;
        if ($feeAmount > 0) {
            $this->commissionService->recordToLedger(
                sourceType:  'withdrawal',
                sourceId:    $result->id,
                userId:      $result->user_id,
                amount:      $feeAmount,
                rate:        (float) $result->commission_rate,
                description: "Comisión de retiro — {$result->currency}",
            );
        }

        $result->load('user')->user->notify(new WithdrawalCompletedNotification($result));

        return $result;
    }

    /**
     * Cancel a pending withdrawal request (user-initiated).
     * Full amount is returned (no fee on cancellations).
     * Only allowed within 1 hour of creation.
     *
     * @throws AuthorizationException
     * @throws InvalidStatusTransitionException
     * @throws \DomainException
     * @throws \Throwable
     */
    public function cancel(WithdrawalRequest $request, User $user): WithdrawalRequest
    {
        if ($request->user_id !== $user->id) {
            throw new AuthorizationException('Solo el propietario puede cancelar esta solicitud.');
        }

        if ($request->cancellationSecondsLeft() === 0) {
            throw new \DomainException('La ventana de cancelación de 1 hora ha expirado.');
        }

        $result = DB::transaction(function () use ($request): WithdrawalRequest {
            $fresh = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            $this->assertStatus($fresh, 'pending');

            if ($fresh->cancellationSecondsLeft() === 0) {
                throw new \DomainException('La ventana de cancelación de 1 hora ha expirado.');
            }

            $fresh->update([
                'status'           => 'cancelled',
                'rejection_reason' => 'Cancelado por el usuario',
            ]);

            $this->releaseToWallet($fresh);

            return $fresh;
        });

        activity()
            ->causedBy($user)
            ->performedOn($result)
            ->log("Usuario {$user->email} canceló retiro {$result->id}");

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @throws InvalidStatusTransitionException
     */
    private function assertStatus(WithdrawalRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw new InvalidStatusTransitionException($request->status, $expected);
        }
    }

    /**
     * @param  array<string> $allowed
     * @throws InvalidStatusTransitionException
     */
    private function assertStatusIn(WithdrawalRequest $request, array $allowed): void
    {
        if (! in_array($request->status, $allowed, strict: true)) {
            throw new InvalidStatusTransitionException($request->status, implode('|', $allowed));
        }
    }

    /**
     * Release the full amount back to the user's wallet.
     * Must run inside an enclosing DB::transaction with the WithdrawalRequest already locked.
     */
    private function releaseToWallet(WithdrawalRequest $request): void
    {
        /** @var Wallet $wallet */
        $wallet = Wallet::where('user_id', $request->user_id)
            ->lockForUpdate()
            ->firstOrFail();

        $newInOperation = bcadd((string) $wallet->balance_in_operation, (string) $request->amount, 8);

        $wallet->update([
            'balance_in_operation' => $newInOperation,
            'balance_total'        => $newInOperation,
        ]);
    }

    /**
     * Create an immutable transaction record for this withdrawal.
     * net_amount reflects what the user actually receives (after commission).
     * Must run inside an enclosing DB::transaction with the WithdrawalRequest already locked.
     */
    private function createWithdrawalTransaction(WithdrawalRequest $request, string $txStatus): void
    {
        Transaction::create([
            'user_id'        => $request->user_id,
            'type'           => 'withdrawal',
            'amount'         => $request->amount,
            'fee_amount'     => $request->fee_amount,
            'net_amount'     => '-' . $request->net_amount,
            'currency'       => $request->currency,
            'status'         => $txStatus,
            'reference_type' => 'withdrawal_request',
            'reference_id'   => $request->id,
            'metadata'       => ['admin_id' => $request->reviewed_by],
        ]);
    }
}
