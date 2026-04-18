<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionFilterDTO;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Models\YieldLog;
use App\Services\TransactionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionQueryService $transactionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = new TransactionFilterDTO(
            types:    $request->has('type') ? (array) $request->input('type') : null,
            statuses: $request->has('status') ? (array) $request->input('status') : null,
            dateFrom: $request->input('date_from'),
            dateTo:   $request->input('date_to'),
            userId:   $user->id,
            perPage:  min($request->integer('per_page', 20), 50),
        );

        $paginated = $this->transactionService->list($filters);

        /** @var Collection<int, Transaction> $items */
        $items = collect($paginated->items());

        $adminNames = $this->resolveAdminNames($items);

        return response()->json([
            'data' => $items->map(function (Transaction $tx) use ($adminNames): array {
                $data = $tx->toArray();
                $data['admin_name'] = $adminNames[$tx->id] ?? null;
                return $data;
            }),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $transaction = $this->transactionService->getById($id);

        if ($transaction->user_id !== $user->id) {
            throw new NotFoundHttpException();
        }

        return response()->json([
            'data' => $transaction,
        ]);
    }

    /**
     * Batch-resolve admin names for a page of transactions.
     * Uses transaction type (not reference_type) so it works regardless of
     * whether reference_type was stored. Falls back to metadata['admin_id']
     * for older records that predate reference_id population.
     *
     * @param  Collection<int, Transaction> $transactions
     * @return array<string, string|null>  keyed by transaction id
     */
    private function resolveAdminNames(Collection $transactions): array
    {
        $result = [];

        // ── yield → YieldLog.applied_by ──────────────────────────────────────
        $yieldTxs = $transactions->where('type', 'yield');

        if ($yieldTxs->isNotEmpty()) {
            // Primary: have reference_id pointing to yield_log
            $withRef = $yieldTxs->whereNotNull('reference_id');
            if ($withRef->isNotEmpty()) {
                $yieldLogIds = $withRef->pluck('reference_id', 'id');
                $yieldLogs = YieldLog::with('appliedBy:id,name')
                    ->whereIn('id', $yieldLogIds->values())
                    ->get()
                    ->keyBy('id');
                foreach ($yieldLogIds as $txId => $logId) {
                    $result[$txId] = $yieldLogs->get($logId)?->appliedBy?->name;
                }
            }

            // Fallback: no reference_id — match via user_id + amount + date
            $withoutRef = $yieldTxs->whereNull('reference_id');
            if ($withoutRef->isNotEmpty()) {
                foreach ($withoutRef as $tx) {
                    if (isset($result[$tx->id])) {
                        continue;
                    }
                    $adminId = $tx->metadata['admin_id'] ?? null;
                    if ($adminId) {
                        $result[$tx->id] = Admin::find($adminId)?->name;
                        continue;
                    }
                    $log = \App\Models\YieldLogUser::with('yieldLog.appliedBy:id,name')
                        ->where('user_id', $tx->user_id)
                        ->whereDate('created_at', $tx->created_at->toDateString())
                        ->first();
                    $result[$tx->id] = $log?->yieldLog?->appliedBy?->name;
                }
            }
        }

        // ── withdrawal → WithdrawalRequest.reviewer ──────────────────────────
        $withdrawalTxs = $transactions->where('type', 'withdrawal');

        if ($withdrawalTxs->isNotEmpty()) {
            // Primary: have reference_id pointing to withdrawal_request
            $withRef = $withdrawalTxs->whereNotNull('reference_id');
            if ($withRef->isNotEmpty()) {
                $requestIds = $withRef->pluck('reference_id', 'id');
                $requests = WithdrawalRequest::with('reviewer:id,name')
                    ->whereIn('id', $requestIds->values())
                    ->get()
                    ->keyBy('id');
                foreach ($requestIds as $txId => $requestId) {
                    $result[$txId] = $requests->get($requestId)?->reviewer?->name;
                }
            }

            // Fallback: no reference_id — match via user_id + amount
            $withoutRef = $withdrawalTxs->whereNull('reference_id');
            if ($withoutRef->isNotEmpty()) {
                foreach ($withoutRef as $tx) {
                    if (isset($result[$tx->id])) {
                        continue;
                    }
                    $adminId = $tx->metadata['admin_id'] ?? null;
                    if ($adminId) {
                        $result[$tx->id] = Admin::find($adminId)?->name;
                        continue;
                    }
                    $req = WithdrawalRequest::with('reviewer:id,name')
                        ->where('user_id', $tx->user_id)
                        ->where('amount', $tx->amount)
                        ->whereNotNull('reviewed_by')
                        ->latest('reviewed_at')
                        ->first();
                    $result[$tx->id] = $req?->reviewer?->name;
                }
            }
        }

        // ── admin_adjustment → metadata['admin_id'] ──────────────────────────
        $adjustments = $transactions->where('type', 'admin_adjustment');

        if ($adjustments->isNotEmpty()) {
            $adminIds = $adjustments
                ->map(fn($tx) => $tx->metadata['admin_id'] ?? null)
                ->filter()
                ->unique()
                ->values();

            if ($adminIds->isNotEmpty()) {
                $admins = Admin::whereIn('id', $adminIds)->pluck('name', 'id');
                foreach ($adjustments as $tx) {
                    $adminId = $tx->metadata['admin_id'] ?? null;
                    $result[$tx->id] = $adminId ? $admins->get($adminId) : null;
                }
            }
        }

        return $result;
    }
}
