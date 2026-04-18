<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalCurrency;
use Illuminate\Http\JsonResponse;

final class WithdrawalCurrencyController extends Controller
{
    /**
     * GET /withdrawals/currencies
     * Active currencies available for withdrawals.
     * Public — no auth required.
     */
    public function index(): JsonResponse
    {
        $currencies = WithdrawalCurrency::active()
            ->ordered()
            ->get()
            ->map(fn (WithdrawalCurrency $c): array => [
                'symbol'  => $c->symbol,
                'name'    => $c->name,
                'network' => $c->network,
            ]);

        return response()->json(['data' => $currencies]);
    }
}
