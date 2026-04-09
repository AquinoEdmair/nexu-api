<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Utils\Mask;
use App\Services\GoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

final class MetricsController extends Controller
{
    private const TTL_GLOBAL = 60;   // 1 minuto para el contador global
    private const TTL_RANKING = 600; // 10 minutos para el ranking

    public function __construct(
        private readonly GoldService $goldService
    ) {}

    /**
     * Obtiene las métricas globales de la plataforma (Datos Reales).
     */
    public function global(): JsonResponse
    {
        $metrics = Cache::remember('metrics:global_data', self::TTL_GLOBAL, function () {
            return [
                'total_investment' => (float) Wallet::sum('balance_in_operation'),
                'active_investors' => (int) Wallet::where('balance_in_operation', '>', 0)->count(),
                'volume_24h'       => (float) \App\Models\DepositInvoice::where('status', 'completed')
                    ->where('created_at', '>=', now()->subDay())
                    ->sum('amount'),
            ];
        });

        return response()->json([
            'total_investment' => $metrics['total_investment'],
            'active_investors' => $metrics['active_investors'],
            'volume_24h'       => $metrics['volume_24h'],
            'currency'         => 'USD',
            'updated_at'       => now()->toIso8601String(),
        ]);
    }

    /**
     * Obtiene el top 10 de inversores con nombres enmascarados y categorías.
     */
    public function ranking(): JsonResponse
    {
        $ranking = Cache::remember('metrics:user_ranking', self::TTL_RANKING, function () {
            return Wallet::query()
                ->where('balance_in_operation', '>', 0)
                ->with(['user' => function ($query) {
                    $query->select('id', 'name')->withSum('elitePoints', 'points');
                }])
                ->orderByDesc('balance_in_operation')
                ->limit(10)
                ->get()
                ->map(function ($wallet) {
                    $points = (float) ($wallet->user->elite_points_sum_points ?? 0);
                    
                    return [
                        'user_name' => Mask::name($wallet->user->name),
                        'amount'    => (float) $wallet->balance_in_operation,
                        'category'  => $this->resolveEliteLevel($points),
                        'level_pts' => $points,
                    ];
                });
        });

        return response()->json([
            'data'       => $ranking,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Obtiene el comportamiento histórico del precio del oro (Datos Reales).
     */
    public function gold(): JsonResponse
    {
        $priceData = $this->goldService->getPriceData();

        return response()->json($priceData);
    }

    /**
     * Obtiene las últimas noticias relacionadas al oro y finanzas.
     */
    public function news(): JsonResponse
    {
        $news = $this->goldService->getGoldNews();

        return response()->json([
            'data'       => $news,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    private function resolveEliteLevel(float $points): string
    {
        return match (true) {
            $points >= 20000 => 'Platino',
            $points >= 5000  => 'Oro',
            $points >= 1000  => 'Plata',
            default          => 'Bronce',
        };
    }
}
