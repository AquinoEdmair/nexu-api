<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EliteTierService;
use Illuminate\Http\JsonResponse;

final class EliteTierController extends Controller
{
    public function __construct(
        private readonly EliteTierService $tierService,
    ) {}

    /**
     * GET /elite/tiers
     * Returns all active tiers ordered by progression (lowest first).
     * Public endpoint — no auth required.
     */
    public function index(): JsonResponse
    {
        $tiers = $this->tierService->getAll()->map(fn ($tier) => [
            'slug'                          => $tier->slug,
            'name'                          => $tier->name,
            'min_points'                    => number_format((float) $tier->min_points, 2, '.', ''),
            'max_points'                    => $tier->max_points !== null
                                                ? number_format((float) $tier->max_points, 2, '.', '')
                                                : null,
            'multiplier'                    => number_format((float) $tier->multiplier, 2, '.', ''),
            'first_deposit_commission_rate' => number_format((float) $tier->first_deposit_commission_rate, 4, '.', ''),
            'recurring_commission_rate'     => number_format((float) $tier->recurring_commission_rate, 4, '.', ''),
        ]);

        return response()->json(['data' => $tiers]);
    }
}
