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
     * Batch-resolve admin names for a page of transactions. One query per reference type.
     *
     * @param  Collection<int, Transaction> $transactions
     * @return array<string, string|null>  keyed by transaction id
     */
    private function resolveAdminNames(Collection $transactions): array
    {
        $result = [];

        // ── yield_log references ─────────────────────────────────────────────
        $yieldIds = $transactions
            ->where('reference_type', 'yield_log')
            ->pluck('reference_id', 'id');

        if ($yieldIds->isNotEmpty()) {
            $yieldLogs = YieldLog::with('appliedBy:id,name')
                ->whereIn('id', $yieldIds->values())
                ->get()
                ->keyBy('id');

            foreach ($yieldIds as $txId => $yieldLogId) {
                $result[$txId] = $yieldLogs->get($yieldLogId)?->appliedBy?->name;
            }
        }

        // ── withdrawal_request references ────────────────────────────────────
        $withdrawalIds = $transactions
            ->where('reference_type', 'withdrawal_request')
            ->pluck('reference_id', 'id');

        if ($withdrawalIds->isNotEmpty()) {
            $requests = WithdrawalRequest::with('reviewer:id,name')
                ->whereIn('id', $withdrawalIds->values())
                ->get()
                ->keyBy('id');

            foreach ($withdrawalIds as $txId => $requestId) {
                $result[$txId] = $requests->get($requestId)?->reviewer?->name;
            }
        }

        // ── admin_adjustment (admin_id stored in metadata) ───────────────────
        $adjustments = $transactions->where('type', 'admin_adjustment');

        if ($adjustments->isNotEmpty()) {
            $adminIds = $adjustments
                ->pluck('metadata.admin_id')
                ->filter()
                ->unique()
                ->values();

            if ($adminIds->isNotEmpty()) {
                $admins = Admin::whereIn('id', $adminIds)
                    ->pluck('name', 'id');

                foreach ($adjustments as $tx) {
                    $adminId = $tx->metadata['admin_id'] ?? null;
                    $result[$tx->id] = $adminId ? $admins->get($adminId) : null;
                }
            }
        }

        return $result;
    }
}
