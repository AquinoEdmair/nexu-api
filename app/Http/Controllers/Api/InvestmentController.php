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
     * Deprecated: all funds are permanently in operation.
     * No available→in_operation transfer is needed.
     *
     * POST /api/v1/investments
     */
    public function store(InvestRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Este endpoint ha sido eliminado. Todo el saldo ya está en operación automáticamente.',
        ], 410);
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
