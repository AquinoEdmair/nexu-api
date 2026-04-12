<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvestRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvestmentController extends Controller
{
    public function __construct(
        private readonly InvestmentService $investmentService,
    ) {}

    /**
     * Move funds from balance_available to balance_in_operation.
     *
     * POST /api/v1/investments
     */
    public function store(InvestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user   = $request->user();
        $amount = (float) $request->validated('amount');

        try {
            $tx = $this->investmentService->invest($user, $amount);
        } catch (InsufficientBalanceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        // Reload wallet for updated balances in response
        $user->load('wallet');

        return response()->json([
            'data' => [
                'transaction' => $this->formatTransaction($tx),
                'wallet'      => [
                    'balance_available'    => $user->wallet->balance_available,
                    'balance_in_operation' => $user->wallet->balance_in_operation,
                    'balance_total'        => $user->wallet->balance_total,
                ],
            ],
            'message' => __('Inversión realizada con éxito.'),
        ], 201);
    }

    /**
     * List investment transactions for the authenticated user.
     *
     * GET /api/v1/investments
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $perPage = min($request->integer('per_page', 20), 50);

        $paginated = Transaction::where('user_id', $user->id)
            ->where('type', 'investment')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($tx) => $this->formatTransaction($tx)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function formatTransaction(Transaction $tx): array
    {
        return [
            'id'         => $tx->id,
            'type'       => $tx->type,
            'amount'     => $tx->amount,
            'net_amount' => $tx->net_amount,
            'currency'   => $tx->currency,
            'status'     => $tx->status,
            'created_at' => $tx->created_at->toIso8601String(),
        ];
    }
}
