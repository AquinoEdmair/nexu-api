<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BalanceController extends Controller
{
    public function __construct(
        private readonly BalanceService $balanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->balanceService->getBalance($user),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $days = max(1, $request->integer('days', 30));

        return response()->json([
            'data' => $this->balanceService->getBalanceHistory($user, $days),
        ]);
    }
}
