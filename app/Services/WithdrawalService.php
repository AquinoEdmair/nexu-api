<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateWithdrawalDTO;
use App\Events\WithdrawalApproved;
use App\Events\WithdrawalRejected;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalCompletedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class WithdrawalService
{
    /**
     * Create a withdrawal request and immediately reserve funds from balance_available.
     *
     * @throws InsufficientBalanceException
     * @throws \Throwable
     */
    public function create(CreateWithdrawalDTO $dto, User $user): WithdrawalRequest
    {
        return DB::transaction(function () use ($dto, $user): WithdrawalRequest {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $dto->amount > (float) $wallet->balance_available) {
                throw new InsufficientBalanceException(
                    (float) $dto->amount,
                    (float) $wallet->balance_available
                );
            }

            $newAvailable = round((float) $wallet->balance_available - (float) $dto->amount, 8);
            $newTotal     = round((float) $wallet->balance_total - (float) $dto->amount, 8);

            $wallet->update([
                'balance_available' => $newAvailable,
                'balance_total'     => $newTotal,
            ]);

            $request = WithdrawalRequest::create([
                'user_id'             => $user->id,
                'amount'              => $dto->amount,
                'currency'            => $dto->currency,
                'destination_address' => $dto->destinationAddress,
                'status'              => 'pending',
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($request)
                ->log("Usuario {$user->email} solicitó retiro por \${$dto->amount}");

            return $request;
        });
    }

    /**
     * Approve a pending withdrawal request.
     *
     * Status is re-checked after acquiring a row lock to prevent concurrent double-approval.
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
     *
     * Status is re-checked after acquiring a row lock to prevent concurrent double-rejection
     * or reject-after-complete race conditions.
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
     * Mark an approved withdrawal request as completed with a blockchain tx hash.
     *
     * Status is re-checked after acquiring a row lock to prevent concurrent double-completion.
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

            $this->createWithdrawalTransaction($fresh, 'confirmed');

            return $fresh;
        });

        activity()
            ->causedBy($admin)
            ->performedOn($result)
            ->log("Admin {$admin->name} completó retiro {$result->id}, tx_hash: {$txHash}");

        $result->load('user')->user->notify(new WithdrawalCompletedNotification($result));

        return $result;
    }

    /**
     * Cancel a pending withdrawal request (user-initiated).
     *
     * Ownership is verified before entering the transaction. Status is re-checked
     * after acquiring a row lock to prevent a cancel/approve race condition.
     *
     * @throws AuthorizationException
     * @throws InvalidStatusTransitionException
     * @throws \Throwable
     */
    public function cancel(WithdrawalRequest $request, User $user): WithdrawalRequest
    {
        if ($request->user_id !== $user->id) {
            throw new AuthorizationException('Solo el propietario puede cancelar esta solicitud.');
        }

        $result = DB::transaction(function () use ($request): WithdrawalRequest {
            $fresh = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            $this->assertStatus($fresh, 'pending');

            $fresh->update([
                'status'           => 'rejected',
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
     * Release funds back to the user's wallet.
     * Must run inside an enclosing DB::transaction with the WithdrawalRequest already locked.
     */
    private function releaseToWallet(WithdrawalRequest $request): void
    {
        /** @var Wallet $wallet */
        $wallet = Wallet::where('user_id', $request->user_id)
            ->lockForUpdate()
            ->firstOrFail();

        $wallet->update([
            'balance_available' => round((float) $wallet->balance_available + (float) $request->amount, 8),
            'balance_total'     => round((float) $wallet->balance_total + (float) $request->amount, 8),
        ]);
    }

    /**
     * Create an immutable transaction record for this withdrawal.
     * Must run inside an enclosing DB::transaction with the WithdrawalRequest already locked.
     */
    private function createWithdrawalTransaction(WithdrawalRequest $request, string $txStatus): void
    {
        Transaction::create([
            'user_id'        => $request->user_id,
            'type'           => 'withdrawal',
            'amount'         => $request->amount,
            'fee_amount'     => 0,
            'net_amount'     => '-' . $request->amount,
            'currency'       => $request->currency,
            'status'         => $txStatus,
            'reference_type' => 'withdrawal_request',
            'reference_id'   => $request->id,
        ]);
    }
}
