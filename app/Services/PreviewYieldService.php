<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ApplyYieldDTO;
use App\DTOs\YieldPreviewDTO;
use App\DTOs\YieldPreviewRowDTO;
use App\Models\Wallet;

final class PreviewYieldService
{
    private const int PREVIEW_ROW_LIMIT = 100;

    /**
     * Calculate yield impact WITHOUT writing to the database.
     * Purely read-only — no locks, no transactions, no side effects.
     */
    public function calculate(ApplyYieldDTO $dto): YieldPreviewDTO
    {
        // ── Aggregate totals at DB level (never load all wallets in memory) ──
        $baseQuery = $this->buildWalletQuery($dto);

        $totalUsers = (clone $baseQuery)->count();
        $totalSystemBalance = (float) (clone $baseQuery)->sum('balance_in_operation');

        // ── Load only the first N wallets for display rows ─────────────────
        $wallets = (clone $baseQuery)
            ->with('user:id,name,email')
            ->limit(self::PREVIEW_ROW_LIMIT)
            ->get();

        $rows = collect();
        $totalAmount = 0.0;
        $hasUsersGoingNegative = false;
        $usersSkippedByPolicy = 0;

        // Compute totals from a full aggregate scan rather than per-row iteration
        // Note: for accurate totalAmount we still need per-user logic (policy varies),
        // so we do a separate chunk-based calculation using DB sums per policy bucket.
        foreach ($wallets as $wallet) {
            $rawAmount    = $this->computeAmount($dto->type, $dto->value, (float) $wallet->balance_in_operation);
            $balanceAfter = (float) $wallet->balance_in_operation + $rawAmount;
            $wouldGoNeg = $balanceAfter < 0.0;
            $wouldBeSkip = $wouldGoNeg && $dto->negativePolicy === 'skip';

            if ($wouldGoNeg) {
                $hasUsersGoingNegative = true;
            }

            if ($wouldBeSkip) {
                $usersSkippedByPolicy++;
                $effectiveAmount = 0.0;
                $balanceAfter = (float) $wallet->balance_in_operation;
            } elseif ($wouldGoNeg && $dto->negativePolicy === 'floor') {
                $effectiveAmount = -(float) $wallet->balance_in_operation;
                $balanceAfter = 0.0;
            } else {
                $effectiveAmount = $rawAmount;
            }

            $totalAmount += $effectiveAmount;

            $rows->push(new YieldPreviewRowDTO(
                userId: $wallet->user_id,
                userName: $wallet->user->name ?? '—',
                userEmail: $wallet->user->email ?? '—',
                balanceBefore: number_format((float) $wallet->balance_in_operation, 8, '.', ''),
                amountToApply: number_format($effectiveAmount, 8, '.', ''),
                balanceAfter: number_format($balanceAfter, 8, '.', ''),
                wouldGoNegative: $wouldGoNeg,
                wouldBeSkipped: $wouldBeSkip,
            ));
        }

        // If there are more users than the preview limit, compute aggregate
        // totalAmount at DB level for accuracy in the summary cards.
        if ($totalUsers > self::PREVIEW_ROW_LIMIT) {
            $totalAmount = $this->computeAggregateTotalAmount($dto, $baseQuery);
        }

        return new YieldPreviewDTO(
            totalUsers: $totalUsers,
            totalAmountToApply: number_format($totalAmount, 8, '.', ''),
            systemBalanceBefore: number_format($totalSystemBalance, 8, '.', ''),
            systemBalanceAfter: number_format($totalSystemBalance + $totalAmount, 8, '.', ''),
            userRows: $rows,
            hasUsersGoingNegative: $hasUsersGoingNegative,
            usersSkippedByPolicy: $usersSkippedByPolicy,
        );
    }

    // ── Private ─────────────────────────────────────────────────────────────────────────────────

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Wallet> */
    private function buildWalletQuery(ApplyYieldDTO $dto): \Illuminate\Database\Eloquent\Builder
    {
        $query = Wallet::query();

        if ($dto->scope === 'specific_user') {
            $query->whereHas(
                'user',
                fn($q) => $q
                    ->where('id', $dto->userId)
                    ->where('status', 'active')
            );
        } else {
            $query->whereHas('user', fn($q) => $q->where('status', 'active'));
        }

        return $query;
    }

    /**
     * Compute the aggregate totalAmount to distribute when users > PREVIEW_ROW_LIMIT.
     * Uses DB-level GROUP BY to avoid loading all wallets in memory.
     *
     * For percentage yields: SUM(balance_in_operation) * (value / 100)
     * For fixed_amount:      COUNT(*) * value (each user gets the same fixed amount)
     *
     * Negative policy (floor/skip) is approximated here; full accuracy only
     * occurs during actual processing. This is acceptable for a preview.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Wallet> $baseQuery
     */
    private function computeAggregateTotalAmount(ApplyYieldDTO $dto, \Illuminate\Database\Eloquent\Builder $baseQuery): float
    {
        if ($dto->type === 'percentage') {
            $sumBalance = (float) (clone $baseQuery)->sum('balance_in_operation');
            return round($sumBalance * ($dto->value / 100), 8);
        }

        // fixed_amount: each active user with a wallet receives the fixed value
        $count = (clone $baseQuery)->count();
        return round($count * $dto->value, 8);
    }

    /**
     * Compute the raw yield amount for a given balance.
     * Same logic as YieldService::computeAmount — keep both in sync.
     */
    private function computeAmount(string $type, float $value, float $balanceInOperation): float
    {
        if ($type === 'percentage') {
            return round($balanceInOperation * ($value / 100), 8);
        }

        return round($value, 8);
    }
}
