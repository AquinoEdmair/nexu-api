<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ApplyYieldDTO;
use App\Exceptions\BalanceInvariantViolationException;
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

    /**
     * @throws BalanceInvariantViolationException
     * @throws \Throwable
     */
    private function applyToUser(YieldLog $yieldLog, string $userId): void
    {
        DB::transaction(function () use ($yieldLog, $userId): void {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $userId)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotency: skip if already processed for this yield_log
            if (YieldLogUser::where('yield_log_id', $yieldLog->id)->where('user_id', $userId)->exists()) {
                return;
            }

            $balanceBefore = (float) $wallet->balance_in_operation;
            $amount        = $this->computeAmount($yieldLog->type, (float) $yieldLog->value, $balanceBefore);
            $balanceAfter = $balanceBefore + $amount;
            $status = 'applied';

            if ($balanceAfter < 0.0) {
                if ($yieldLog->negative_policy === 'skip') {
                    $status = 'skipped';
                    $amount = 0.0;
                    $balanceAfter = $balanceBefore;
                } else {
                    // floor: apply only down to zero
                    $amount = -$balanceBefore;
                    $balanceAfter = 0.0;
                }
            }

            if ($status === 'applied' && $amount !== 0.0) {
                $newTotal = round($balanceAfter, 8);

                // Invariant guard: verify DB-recorded balance_total equals
                // balance_in_operation BEFORE the update.
                // A mismatch means data corruption in a prior operation.
                $recordedTotal = round((float) $wallet->balance_total, 8);
                if (abs($recordedTotal - round($balanceBefore, 8)) > 0.000000009) {
                    throw new BalanceInvariantViolationException(
                        $userId,
                        '0',
                        (string) $balanceBefore,
                        (string) $wallet->balance_total,
                    );
                }

                $wallet->update([
                    'balance_in_operation' => $newTotal,
                    'balance_total'        => $newTotal,
                ]);

                Transaction::create([
                    'user_id'        => $userId,
                    'wallet_id'      => $wallet->id,
                    'type'           => 'yield',
                    'amount'         => round(abs($amount), 8),
                    'fee_amount'     => 0,
                    'net_amount'     => round($amount, 8),
                    'currency'       => 'USD',
                    'status'         => 'confirmed',
                    'reference_type' => 'yield_log',
                    'reference_id'   => $yieldLog->id,
                    'metadata'       => ['admin_id' => $yieldLog->applied_by],
                ]);
            }

            YieldLogUser::create([
                'yield_log_id' => $yieldLog->id,
                'user_id' => $userId,
                'balance_before' => round($balanceBefore, 8),
                'balance_after' => round($balanceAfter, 8),
                'amount_applied' => round($amount, 8),
                'status' => $status,
            ]);
        });
    }

    /**
     * Compute the raw yield amount for a given balance.
     * Same logic as PreviewYieldService::computeAmount — keep both in sync.
     */
    private function computeAmount(string $type, float $value, float $balanceInOperation): float
    {
        if ($type === 'percentage') {
            return round($balanceInOperation * ($value / 100), 8);
        }

        return round($value, 8);
    }
}
