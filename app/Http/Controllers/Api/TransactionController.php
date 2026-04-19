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
     * Batch-resolve admin names for a page of transactions without N+1.
     *
     * @param  Collection<int, Transaction> $transactions
     * @return array<string, string|null>  keyed by transaction id
     */
    private function resolveAdminNames(Collection $transactions): array
    {
        $result       = [];
        $yieldRefs    = [];   // txId => yield_log_id
        $withdrawRefs = [];   // txId => withdrawal_request_id
        $adjustAdmins = [];   // txId => admin_id

        foreach ($transactions as $tx) {
            if ($tx->type === 'yield' && $tx->reference_id !== null) {
                $yieldRefs[(string) $tx->id] = (string) $tx->reference_id;
            } elseif ($tx->type === 'withdrawal' && $tx->reference_id !== null) {
                $withdrawRefs[(string) $tx->id] = (string) $tx->reference_id;
            } elseif ($tx->type === 'admin_adjustment') {
                $adminId = is_array($tx->metadata) ? ($tx->metadata['admin_id'] ?? null) : null;
                if ($adminId !== null) {
                    $adjustAdmins[(string) $tx->id] = (string) $adminId;
                }
            }
        }

        // ── yield → YieldLog → appliedBy ────────────────────────────────────
        if (!empty($yieldRefs)) {
            $logs = YieldLog::with('appliedBy:id,name')
                ->whereIn('id', array_values($yieldRefs))
                ->get()
                ->keyBy('id');

            foreach ($yieldRefs as $txId => $logId) {
                $result[$txId] = $logs->get($logId)?->appliedBy?->name;
            }
        }

        // ── withdrawal → WithdrawalRequest → reviewer ────────────────────────
        if (!empty($withdrawRefs)) {
            $requests = WithdrawalRequest::with('reviewer:id,name')
                ->whereIn('id', array_values($withdrawRefs))
                ->get()
                ->keyBy('id');

            foreach ($withdrawRefs as $txId => $requestId) {
                $result[$txId] = $requests->get($requestId)?->reviewer?->name;
            }
        }

        // ── admin_adjustment → Admin ─────────────────────────────────────────
        if (!empty($adjustAdmins)) {
            $admins = Admin::whereIn('id', array_unique(array_values($adjustAdmins)))
                ->pluck('name', 'id');

            foreach ($adjustAdmins as $txId => $adminId) {
                $result[$txId] = $admins->get($adminId);
            }
        }

        return $result;
    }
}
