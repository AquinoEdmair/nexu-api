<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BalanceSnapshot;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class BalanceService
{
    private const DEFAULT_CURRENCY = 'USD';

    /**
     * Returns the current balance for a user.
     *
     * @return array{balance_in_operation: string, balance_total: string, currency: string}
     */
    public function getBalance(User $user): array
    {
        $wallet = $user->wallet;

        if ($wallet === null) {
            return [
                'balance_in_operation' => '0.00000000',
                'balance_total'        => '0.00000000',
                'currency'             => self::DEFAULT_CURRENCY,
            ];
        }

        return [
            'balance_in_operation' => $wallet->balance_in_operation,
            'balance_total'        => $wallet->balance_total,
            'currency'             => self::DEFAULT_CURRENCY,
        ];
    }

    /**
     * Returns daily balance history for chart display.
     * Includes today's live data from the wallet (not a snapshot).
     *
     * @return Collection<int, array{date: string, balance_total: string, balance_in_operation: string}>
     */
    public function getBalanceHistory(User $user, int $days = 30): Collection
    {
        $days = min($days, 365);
        $since = Carbon::today()->subDays($days);

        $snapshots = BalanceSnapshot::where('user_id', $user->id)
            ->where('snapshot_date', '>=', $since->toDateString())
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn (BalanceSnapshot $s): array => [
                'date'                 => $s->snapshot_date->format('Y-m-d'),
                'balance_total'        => $s->balance_total,
                'balance_in_operation' => $s->balance_in_operation,
            ]);

        // Append today's live data from wallet
        $wallet = $user->wallet;

        if ($wallet !== null) {
            $today = Carbon::today()->format('Y-m-d');
            $hasToday = $snapshots->contains(fn (array $entry): bool => $entry['date'] === $today);

            if (! $hasToday) {
                $snapshots->push([
                    'date'                 => $today,
                    'balance_total'        => $wallet->balance_total,
                    'balance_in_operation' => $wallet->balance_in_operation,
                ]);
            }
        }

        return $snapshots;
    }

    /**
     * Takes a snapshot of all wallets for today.
     * Called by SnapshotDailyBalances Job.
     * Idempotent: skips users that already have a snapshot for today.
     */
    public function snapshotAllBalances(): int
    {
        $today = Carbon::today()->toDateString();
        $count = 0;

        Wallet::query()
            ->chunk(100, function ($wallets) use ($today, &$count): void {
                $userIds = $wallets->pluck('user_id')->all();

                $alreadySnapshotted = BalanceSnapshot::where('snapshot_date', $today)
                    ->whereIn('user_id', $userIds)
                    ->pluck('user_id')
                    ->flip();

                foreach ($wallets as $wallet) {
                    /** @var Wallet $wallet */
                    if ($alreadySnapshotted->has($wallet->user_id)) {
                        continue;
                    }

                    BalanceSnapshot::create([
                        'user_id'              => $wallet->user_id,
                        'balance_in_operation' => $wallet->balance_in_operation,
                        'balance_total'        => $wallet->balance_total,
                        'snapshot_date'        => $today,
                        'created_at'           => now(),
                    ]);

                    $count++;
                }
            });

        return $count;
    }
}
