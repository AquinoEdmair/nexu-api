<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TransactionFilterDTO;
use App\DTOs\TransactionSummaryDTO;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class TransactionQueryService
{
    /**
     * Returns a paginated, filtered list of transactions.
     * Eager-loads user (name + email only) to avoid N+1.
     *
     * @return LengthAwarePaginator<Transaction>
     */
    public function list(TransactionFilterDTO $filters): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->with(['user:id,name,email,status'])
            ->latest('created_at')
            ->paginate($filters->perPage);
    }

    /**
     * Returns a single transaction with full relations resolved.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getById(string $id): Transaction
    {
        /** @var Transaction */
        return Transaction::with([
            'user:id,name,email,status,phone',
            'wallet:id,user_id,balance_available,balance_in_operation,balance_total',
        ])->findOrFail($id);
    }

    /**
     * Counts records matching the filters.
     * Used to decide between sync and async exports.
     */
    public function estimateCount(TransactionFilterDTO $filters): int
    {
        return $this->buildQuery($filters)->count();
    }

    /**
     * Returns the raw Builder for large exports (used by ExportTransactionsJob).
     *
     * @return Builder<Transaction>
     */
    public function exportQuery(TransactionFilterDTO $filters): Builder
    {
        return $this->buildQuery($filters)
            ->with(['user:id,name,email'])
            ->latest('created_at');
    }

    /**
     * Aggregates net_amount per type for the filter result set.
     */
    public function getSummaryTotals(TransactionFilterDTO $filters): TransactionSummaryDTO
    {
        $rows = $this->buildQuery($filters)
            ->selectRaw('type, SUM(net_amount) as total, COUNT(*) as cnt')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Derive total count from GROUP BY rows — avoids a second COUNT(*) query.
        $count = $rows->sum('cnt');

        return new TransactionSummaryDTO(
            totalDeposits:             (string) ($rows->get('deposit')?->total         ?? '0'),
            totalWithdrawals:          (string) ($rows->get('withdrawal')?->total       ?? '0'),
            totalYields:               (string) ($rows->get('yield')?->total            ?? '0'),
            totalCommissions:          (string) ($rows->get('commission')?->total       ?? '0'),
            totalReferralCommissions:  (string) ($rows->get('referral_commission')?->total ?? '0'),
            transactionCount:          (int) $count,
        );
    }

    // ── Private ──────────────────────────────────────────────────────────────

    /** @return Builder<Transaction> */
    private function buildQuery(TransactionFilterDTO $filters): Builder
    {
        $query = Transaction::query();

        if ($filters->types !== null) {
            $query->byType($filters->types);
        }

        if ($filters->statuses !== null) {
            $query->byStatus($filters->statuses);
        }

        if ($filters->currency !== null) {
            $query->byCurrency($filters->currency);
        }

        if ($filters->dateFrom !== null && $filters->dateTo !== null) {
            $query->betweenDates($filters->dateFrom, $filters->dateTo);
        } elseif ($filters->dateFrom !== null) {
            $query->where('created_at', '>=', $filters->dateFrom);
        } elseif ($filters->dateTo !== null) {
            $query->where('created_at', '<=', $filters->dateTo);
        }

        if ($filters->amountMin !== null && $filters->amountMax !== null) {
            $query->amountBetween($filters->amountMin, $filters->amountMax);
        } elseif ($filters->amountMin !== null) {
            $query->where('net_amount', '>=', $filters->amountMin);
        } elseif ($filters->amountMax !== null) {
            $query->where('net_amount', '<=', $filters->amountMax);
        }

        if ($filters->userId !== null) {
            $query->forUser($filters->userId);
        }

        if ($filters->search !== null) {
            $term = $filters->search;
            $query->where(function (Builder $q) use ($term): void {
                $q->where('external_tx_id', 'like', "%{$term}%")
                  ->orWhereHas('user', function (Builder $u) use ($term): void {
                      $u->where('email', 'like', "%{$term}%")
                        ->orWhere('name', 'like', "%{$term}%");
                  });
            });
        }

        return $query;
    }
}
