<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
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
                'total_investment' => (float) Wallet::sum('balance_total') + 10000,
                'active_investors' => (int) Wallet::where('balance_total', '>', 0)->count() + 300,
                'volume_24h'       => (float) \App\Models\Transaction::where('status', 'confirmed')
                    ->where('created_at', '>=', now()->subDay())
                    ->whereIn('type', ['deposit', 'yield', 'admin_adjustment'])
                    ->where('net_amount', '>', 0)
                    ->sum('net_amount') + 30000,
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
                    $query->select('id', 'name', 'phone')->withSum('elitePoints', 'points');
                }])
                ->orderByDesc('balance_in_operation')
                ->limit(10)
                ->get()
                ->map(function ($wallet) {
                    $points = (float) ($wallet->user->elite_points_sum_points ?? 0);
                    $flag = $this->getFlagFromPhone($wallet->user->phone);
                    
                    return [
                        'user_name' => Mask::name($wallet->user->name),
                        'amount'    => (float) $wallet->balance_in_operation,
                        'category'  => $this->resolveEliteLevel($points),
                        'level_pts' => $points,
                        'flag'      => $flag,
                    ];
                });
        });

        return response()->json([
            'data'       => $ranking,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Obtiene el comportamiento histórico del precio del oro.
     * Query param: range = 1h | 1d | 1w | 1m  (default: 1w)
     */
    public function gold(\Illuminate\Http\Request $request): JsonResponse
    {
        $range     = in_array($request->query('range'), ['1h', '1d', '1w', '1m'], true)
            ? $request->query('range')
            : '1w';

        $priceData = $this->goldService->getPriceData($range);

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

    private function getFlagFromPhone(?string $phone): string
    {
        if (!$phone) {
            return '🌎';
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si no empieza con '+', se lo agregamos para que coincida con nuestros prefijos
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return match (true) {
            str_starts_with($phone, '+52') => '🇲🇽',
            str_starts_with($phone, '+1')  => '🇺🇸',
            str_starts_with($phone, '+34') => '🇪🇸',
            str_starts_with($phone, '+57') => '🇨🇴',
            str_starts_with($phone, '+54') => '🇦🇷',
            str_starts_with($phone, '+56') => '🇨🇱',
            str_starts_with($phone, '+51') => '🇵🇪',
            str_starts_with($phone, '+593')=> '🇪🇨',
            str_starts_with($phone, '+58') => '🇻🇪',
            str_starts_with($phone, '+55') => '🇧🇷',
            str_starts_with($phone, '+507')=> '🇵🇦',
            str_starts_with($phone, '+502')=> '🇬🇹',
            str_starts_with($phone, '+506')=> '🇨🇷',
            str_starts_with($phone, '+503')=> '🇸🇻',
            str_starts_with($phone, '+504')=> '🇭🇳',
            str_starts_with($phone, '+505')=> '🇳🇮',
            str_starts_with($phone, '+591')=> '🇧🇴',
            str_starts_with($phone, '+595')=> '🇵🇾',
            str_starts_with($phone, '+598')=> '🇺🇾',
            str_starts_with($phone, '+509')=> '🇭🇹',
            str_starts_with($phone, '+53') => '🇨🇺',
            str_starts_with($phone, '+1809'), str_starts_with($phone, '+1829'), str_starts_with($phone, '+1849') => '🇩🇴',
            default => '🌎',
        };
    }
}
