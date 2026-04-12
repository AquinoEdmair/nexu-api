<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateReferralCodeRequest;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    /**
     * GET /referrals/summary
     * Returns the authenticated user's referral stats, code, and Elite tier.
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->referralService->getSummary($user),
        ]);
    }

    /**
     * GET /referrals/network
     * Paginated list of referred users.
     */
    public function network(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $page    = max(1, $request->integer('page', 1));
        $perPage = max(5, min(50, $request->integer('per_page', 15)));

        $paginator = $this->referralService->getNetwork($user, $page, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /referrals/earnings
     * Paginated history of referral_commission transactions.
     */
    public function earnings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $page    = max(1, $request->integer('page', 1));
        $perPage = max(5, min(50, $request->integer('per_page', 20)));

        $paginator = $this->referralService->getEarnings($user, $page, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /referrals/points-history
     * Paginated history of how the user earned Elite points.
     */
    public function pointsHistory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $page    = max(1, $request->integer('page', 1));
        $perPage = max(5, min(50, $request->integer('per_page', 20)));

        $paginator = $this->referralService->getPointsHistory($user, $page, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * POST /auth/validate-referral-code   (public — no auth required)
     * Returns whether a code is valid and the masked referrer name.
     */
    public function validateCode(ValidateReferralCodeRequest $request): JsonResponse
    {
        $result = $this->referralService->validateCode($request->string('code')->value());

        return response()->json(['data' => $result]);
    }
}
