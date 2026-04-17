<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\CreateWithdrawalDTO;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateWithdrawalRequest;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\CommissionService;
use App\Services\WithdrawalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WithdrawalController extends Controller
{
    public function __construct(
        private readonly WithdrawalService $withdrawalService,
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * Return the active withdrawal commission rate and breakdown preview.
     */
    public function commissionRate(Request $request): JsonResponse
    {
        $rate      = $this->commissionService->getActiveRate('withdrawal');
        $amount    = (float) $request->query('amount', 0);
        $fee       = round($amount * $rate / 100, 8);
        $netAmount = round($amount - $fee, 8);

        return response()->json([
            'data' => [
                'rate'       => $rate,
                'amount'     => $amount,     // requested amount
                'fee_amount' => $fee,        // commission kept by admin
                'net_amount' => $netAmount,  // what user actually receives
            ],
        ]);
    }

    /**
     * Create a withdrawal request and reserve funds.
     */
    public function store(CreateWithdrawalRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $dto = new CreateWithdrawalDTO(
            amount:             (float) $request->validated('amount'),
            currency:           $request->validated('currency'),
            destinationAddress: $request->validated('destination_address'),
        );

        try {
            $withdrawal = $this->withdrawalService->create($dto, $user);
        } catch (InsufficientBalanceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $this->formatWithdrawal($withdrawal),
        ], 201);
    }

    /**
     * List withdrawal requests for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = WithdrawalRequest::where('user_id', $user->id)
            ->orderByDesc('created_at');

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $perPage = min($request->integer('per_page', 20), 50);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($w) => $this->formatWithdrawal($w)),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * Cancel a pending withdrawal request.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $withdrawal = WithdrawalRequest::findOrFail($id);

        try {
            $this->withdrawalService->cancel($withdrawal, $user);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (InvalidStatusTransitionException) {
            return response()->json([
                'message' => 'No se puede cancelar: el retiro ya no está en estado pendiente.',
            ], 409);
        }

        return response()->json([
            'message' => __('Solicitud de retiro cancelada.'),
        ]);
    }

    /**
     * Format a withdrawal request for API response.
     *
     * @return array<string, mixed>
     */
    private function formatWithdrawal(WithdrawalRequest $w): array
    {
        return [
            'id'                  => $w->id,
            'amount'              => $w->amount,
            'fee_amount'          => $w->fee_amount,
            'net_amount'          => $w->net_amount,
            'commission_rate'     => $w->commission_rate,
            'currency'            => $w->currency,
            'destination_address' => $w->destination_address,
            'status'              => $w->status,
            'reviewed_by'         => $w->reviewed_by,
            'reviewed_at'         => $w->reviewed_at?->toIso8601String(),
            'rejection_reason'    => $w->rejection_reason,
            'tx_hash'             => $w->tx_hash,
            'created_at'          => $w->created_at->toIso8601String(),
            'updated_at'          => $w->updated_at->toIso8601String(),
        ];
    }
}
