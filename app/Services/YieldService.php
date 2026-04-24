<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ApplyYieldDTO;
use App\Jobs\ApplyYieldToUsers;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\YieldLog;
use App\Models\YieldLogUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class YieldService
{
    /**
     * Create a YieldLog record and dispatch the processing Job.
     *
     * @throws \Throwable
     */
    public function createAndDispatch(ApplyYieldDTO $dto, Admin $admin): YieldLog
    {
        $yieldLog = DB::transaction(function () use ($dto, $admin): YieldLog {
            return YieldLog::create([
                'applied_by' => $admin->id,
                'type' => $dto->type,
                'value' => $dto->value,
                'scope' => $dto->scope,
                'negative_policy' => $dto->negativePolicy,
                'specific_user_id' => $dto->userId,
                'description' => $dto->description,
                'status' => 'processing',
                'applied_at' => now(),
            ]);
        });

        activity()
            ->causedBy($admin)
            ->performedOn($yieldLog)
            ->log('yield_log.created');

        ApplyYieldToUsers::dispatch($yieldLog->id, $dto->scope, $dto->userId)->onQueue('yields');

        return $yieldLog;
    }

    /**
     * Apply yield to a batch of user IDs.
     *
     * Each user gets its own DB::transaction. A failure on user N does NOT
     * roll back already-committed users 1..N-1.
     *
     * @param  array<string> $userIds
     * @throws \RuntimeException if failure rate exceeds 10% of the batch
     */
    public function applyBatch(YieldLog $yieldLog, array $userIds): void
    {
        $failures = 0;

        foreach ($userIds as $userId) {
            try {
                $this->applyToUser($yieldLog, $userId);
            } catch (Throwable $e) {
                $failures++;
                Log::error('YieldService: failed to apply yield to user', [
                    'yield_log_id' => $yieldLog->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);

                YieldLogUser::updateOrCreate(
                    ['yield_log_id' => $yieldLog->id, 'user_id' => $userId],
                    [
                        'balance_before' => 0,
                        'balance_after'  => 0,
                        'amount_applied' => 0,
                        'status'         => 'failed',
                        'error_message'  => mb_substr($e->getMessage(), 0, 1000),
                    ]
                );
            }
        }

        $total = count($userIds);
        if ($total > 0 && ($failures / $total) > 0.10) {
            throw new \RuntimeException(
                "Batch failure rate too high: {$failures}/{$total} users failed for yield log {$yieldLog->id}"
            );
        }
    }

    /**
     * Mark a YieldLog as completed, computing totals from yield_log_users.
     */
    public function markCompleted(YieldLog $yieldLog): void
    {
        $totals = YieldLogUser::where('yield_log_id', $yieldLog->id)
            ->where('status', 'applied')
            ->selectRaw('COUNT(*) as cnt, SUM(amount_applied) as total')
            ->first();

        $yieldLog->update([
            'status' => 'completed',
            'users_count' => (int) ($totals->cnt ?? 0),
            'total_applied' => (string) ($totals->total ?? '0'),
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark a YieldLog as failed with an error message.
     */
    public function markFailed(YieldLog $yieldLog, string $error): void
    {
        $yieldLog->update([
            'status' => 'failed',
            'error_message' => mb_substr($error, 0, 2000),
        ]);
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /** @throws \Throwable */
    private function applyToUser(YieldLog $yieldLog, string $userId): void
    {
        DB::transaction(function () use ($yieldLog, $userId): void {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: skip if already processed (applied or skipped) for this yield_log.
            // Lock the check inside the wallet-lock transaction to prevent race conditions.
            $existing = YieldLogUser::where('yield_log_id', $yieldLog->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, ['applied', 'skipped'], strict: true)) {
                return;
            }

            $balanceBefore = (string) $wallet->balance_in_operation;
            $amount        = $this->computeAmountBc($yieldLog->type, (string) $yieldLog->value, $balanceBefore);
            $balanceAfter  = bcadd($balanceBefore, $amount, 8);
            $status        = 'applied';

            if (bccomp($balanceAfter, '0', 8) < 0) {
                if ($yieldLog->negative_policy === 'skip') {
                    $status       = 'skipped';
                    $amount       = '0.00000000';
                    $balanceAfter = $balanceBefore;
                } else {
                    // floor: apply only down to zero
                    $amount       = bcmul($balanceBefore, '-1', 8);
                    $balanceAfter = '0.00000000';
                }
            }

            if ($status === 'applied' && bccomp($amount, '0', 8) !== 0) {
                $wallet->update([
                    'balance_in_operation' => $balanceAfter,
                    'balance_total'        => $balanceAfter,
                ]);

                Transaction::create([
                    'user_id'        => $userId,
                    'wallet_id'      => $wallet->id,
                    'type'           => 'yield',
                    'amount'         => str_replace('-', '', $amount), // absolute value
                    'fee_amount'     => '0.00000000',
                    'net_amount'     => $amount,
                    'currency'       => 'USD',
                    'status'         => 'confirmed',
                    'reference_type' => 'yield_log',
                    'reference_id'   => $yieldLog->id,
                    'metadata'       => ['admin_id' => $yieldLog->applied_by],
                ]);
            }

            // Upsert: if a previous 'failed' record exists, replace it; otherwise insert.
            YieldLogUser::updateOrCreate(
                ['yield_log_id' => $yieldLog->id, 'user_id' => $userId],
                [
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'amount_applied' => $amount,
                    'status'         => $status,
                    'error_message'  => null,
                ]
            );
        });
    }

    /**
     * Compute the raw yield amount using bcmath for precision.
     * Keeps same logic as PreviewYieldService::computeAmount.
     */
    private function computeAmountBc(string $type, string $value, string $balanceInOperation): string
    {
        if ($type === 'percentage') {
            // amount = balance * (value / 100)
            $rate = bcdiv($value, '100', 10);
            return bcmul($balanceInOperation, $rate, 8);
        }

        return bcdiv($value, '1', 8); // normalize to 8 decimal places
    }
}
