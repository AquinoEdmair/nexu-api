<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmDepositRequest;
use App\Http\Requests\CreateDepositRequest;
use App\Models\DepositRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DepositService;
use App\Services\CommissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $depositService,
        private readonly CommissionService $commissionService,
    ) {}

    public function currencies(): JsonResponse
    {
        $currencies = $this->depositService->getActiveCurrencies();
        $minimum    = (float) SystemSetting::get('minimum_deposit_amount', '0');

        return response()->json([
            'data'    => $currencies->map(fn ($c) => [
                'symbol'  => $c->symbol,
                'name'    => $c->name,
                'network' => $c->network,
            ]),
            'minimum_deposit_amount' => $minimum,
        ]);
    }

    public function commissionRate(Request $request): JsonResponse
    {
        $rate        = $this->commissionService->getActiveRate('deposit');
        $amount      = (float) $request->query('amount', 0);
        
        $rateDecimal = bcdiv((string) $rate, '100', 10);
        $feeAmount   = bcmul((string) $amount, $rateDecimal, 8);
        $netAmount   = bcsub((string) $amount, $feeAmount, 8);

        return response()->json([
            'data' => [
                'rate'           => (float) $rate,
                'net_amount'     => (float) $netAmount,
                'fee_amount'     => (float) $feeAmount,
                'amount_charged' => $amount,
            ],
        ]);
    }

    public function store(CreateDepositRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $deposit = $this->depositService->create(
                $user,
                $request->validated('currency'),
                (float) $request->validated('amount'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($deposit->load('reviewer'))], 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $perPage   = min($request->integer('per_page', 20), 50);
        $paginated = $this->depositService->getHistory($user, $request->integer('page', 1), $perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($d) => $this->format($d->load(['reviewer', 'transaction']))),
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
        $user    = $request->user();
        $deposit = DepositRequest::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        return response()->json(['data' => $this->format($deposit->load(['reviewer', 'transaction']))]);
    }

    public function confirm(ConfirmDepositRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $deposit = DepositRequest::findOrFail($id);

        try {
            $this->depositService->clientConfirm($deposit, $user, $request->validated('tx_hash'));
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->format($deposit->fresh()->load(['reviewer', 'transaction']))]);
    }

    /** @return array<string, mixed> */
    private function format(DepositRequest $d): array
    {
        $amount = (float) $d->amount_expected;
        $rate   = $this->commissionService->getActiveRate('deposit');

        if ($d->transaction) {
            $feeAmount = (float) $d->transaction->fee_amount;
            $netAmount = (float) $d->transaction->net_amount;
            $rateUsed  = (float) ($d->transaction->metadata['commission_rate'] ?? $rate);
        } else {
            $feeAmount = round($amount * $rate / 100, 8);
            $netAmount = round($amount - $feeAmount, 8);
            $rateUsed  = $rate;
        }

        return [
            'id'                  => $d->id,
            'currency'            => $d->currency,
            'network'             => $d->network,
            'address'             => $d->address,
            'qr_image_url'        => $d->qr_image_path ? Storage::disk('public')->url($d->qr_image_path) : null,
            'amount_expected'     => (string) $amount,
            'fee_amount'          => (string) $feeAmount,
            'net_amount'          => (string) $netAmount,
            'commission_rate'     => $rateUsed,
            'tx_hash'             => $d->tx_hash,
            'status'              => $d->status,
            'client_confirmed_at' => $d->client_confirmed_at?->toIso8601String(),
            'reviewed_by_name'    => $d->reviewer?->name,
            'reviewed_at'         => $d->reviewed_at?->toIso8601String(),
            'rejection_reason'    => $d->rejection_reason,
            'created_at'          => $d->created_at->toIso8601String(),
            'updated_at'          => $d->updated_at->toIso8601String(),
        ];
    }
}
