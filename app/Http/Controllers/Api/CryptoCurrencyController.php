<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CryptoCurrency;
use Illuminate\Http\JsonResponse;

final class CryptoCurrencyController extends Controller
{
    /**
     * GET /crypto/currencies
     * Returns active currencies available for deposits.
     * Public — no auth required.
     */
    public function index(): JsonResponse
    {
        $currencies = CryptoCurrency::active()
            ->ordered()
            ->get()
            ->map(fn(CryptoCurrency $c): array => [
                'symbol'  => $c->symbol,
                'name'    => $c->name,
                'network' => $c->network,
            ]);

        return response()->json(['data' => $currencies]);
    }
}
