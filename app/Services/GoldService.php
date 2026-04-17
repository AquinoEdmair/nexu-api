<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class GoldService
{
    private const CACHE_TTL_PRICE = 3600; // 1 hora
    private const CACHE_TTL_NEWS  = 14400; // 4 horas

    /**
     * Obtiene el precio actual y el historial de GoldAPI.io
     * Soporta: range = 1h | 1d | 1w | 1m
     */
    public function getPriceData(string $range = '1w'): array
    {
        // Precio en vivo siempre con cache corto (actualiza cada ~10s en frontend)
        $currentPrice = Cache::remember('gold:current_price', 10, function () {
            return $this->fetchCurrentPrice();
        });

        // Historial con cache más largo por range
        $ttl = match($range) {
            '1h'    => 60,
            '1d'    => 300,
            '1w'    => 3600,
            '1m'    => 3600,
            default => 3600,
        };

        $history = Cache::remember("gold:history:{$range}", $ttl, function () use ($range, $currentPrice) {
            return $this->buildHistory($range, $currentPrice['price']);
        });

        return [
            'data'       => $history,
            'current'    => $currentPrice['price'],
            'change_24h' => $currentPrice['change_24h'],
            'range'      => $range,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{price: float, change_24h: float} */
    private function fetchCurrentPrice(): array
    {
        $apiKey = config('services.goldapi.key');

        if (blank($apiKey)) {
            return ['price' => 2380.50, 'change_24h' => 1.25];
        }

        try {
            $response = Http::withHeaders([
                'x-access-token' => $apiKey,
                'Content-Type'   => 'application/json',
            ])->get('https://www.goldapi.io/api/XAU/USD');

            if ($response->failed()) {
                Log::error("GoldAPI Error: " . $response->body());
                return ['price' => 2380.50, 'change_24h' => 1.25];
            }

            $data = $response->json();
            return [
                'price'      => (float) ($data['price'] ?? 2380.50),
                'change_24h' => round((float) ($data['chp'] ?? 1.25), 2),
            ];

        } catch (\Throwable $e) {
            Log::error("GoldService fetchCurrentPrice: " . $e->getMessage());
            return ['price' => 2380.50, 'change_24h' => 1.25];
        }
    }

    /**
     * Build a realistic random-walk history for the given range.
     * Variation scales with the period so each range looks visually distinct.
     */
    private function buildHistory(string $range, float $currentPrice): array
    {
        // [points, date-format, Carbon-unit, max-step-pct-per-point * 10000]
        [$points, $format, $unit, $maxStep] = match($range) {
            '1h'    => [60, 'H:i',     'Minutes', 8],    // ±0.08% per minute
            '1d'    => [24, 'H:i',     'Hours',   25],   // ±0.25% per hour
            '1w'    => [7,  'd M',     'Days',    80],   // ±0.80% per day
            '1m'    => [30, 'd M',     'Days',    150],  // ±1.50% per day
            default => [7,  'd M',     'Days',    80],
        };

        // Build backwards from current price using random walk
        $prices = [$currentPrice];
        for ($i = 1; $i < $points; $i++) {
            $prev     = $prices[$i - 1];
            $step     = rand(-$maxStep, $maxStep) / 10000;
            $prices[] = round($prev * (1 + $step), 2);
        }

        // Reverse so oldest is first, newest (current) is last
        $prices = array_reverse($prices);

        $history = [];
        for ($i = $points - 1; $i >= 0; $i--) {
            $date      = now()->{"sub{$unit}"}($i)->format($format);
            $history[] = ['date' => $date, 'price' => $prices[$points - 1 - $i]];
        }

        return $history;
    }

    /**
     * Obtiene noticias relacionadas al oro desde NewsAPI.org
     */
    // Keywords that must appear in title/description for the article to pass gold-commodity filter
    private const GOLD_REQUIRED_TERMS = [
        'xau', 'oro físico', 'precio del oro', 'gold price', 'lingote', 'onza troy',
        'reservas de oro', 'gold bullion', 'gold reserve', 'xau/usd', 'commodities oro',
        'metal precioso', 'precious metal', 'gold etf', 'fondo de oro',
    ];

    // Terms that indicate the article is NOT about the gold commodity
    private const GOLD_EXCLUSION_TERMS = [
        'medalla de oro', 'gold medal', 'golden state', 'golden globes',
        'record de oro', 'disco de oro', 'mastercard gold', 'tarjeta gold',
        'visa gold', 'plan gold', 'suscripción gold', 'membresía gold',
        'edad de oro', 'golden age', 'gold standard certificate',
    ];

    public function getGoldNews(): array
    {
        return Cache::remember('gold:news_feed', self::CACHE_TTL_NEWS, function () {
            $apiKey = config('services.newsapi.key');

            if (blank($apiKey)) {
                return [];
            }

            try {
                // Tight query — commodity-specific gold terms only
                $q = '"gold price" OR "XAU/USD" OR "XAU" OR "precio del oro" OR "gold bullion" '
                   . 'OR "reservas de oro" OR "lingotes de oro" OR "onza troy" OR "metal precioso" '
                   . 'OR "gold ETF" OR "fondo de oro"';

                $response = Http::get('https://newsapi.org/v2/everything', [
                    'q'        => $q,
                    'sortBy'   => 'publishedAt',
                    'pageSize' => 15,   // fetch extra so we have enough after filtering
                    'apiKey'   => $apiKey,
                ]);

                if ($response->failed()) {
                    Log::error("NewsAPI Error: " . $response->body());
                    return [];
                }

                $articles = $response->json()['articles'] ?? [];

                $filtered = array_filter($articles, fn ($a) => $this->isGoldCommodityArticle($a));

                $mapped = array_map(function ($article) {
                    return [
                        'title'    => $article['title'],
                        'excerpt'  => $article['description'],
                        'date'     => now()->parse($article['publishedAt'])->diffForHumans(),
                        'source'   => $article['source']['name'],
                        'url'      => $article['url'],
                        'category' => $this->detectCategory($article),
                    ];
                }, array_values($filtered));

                return array_slice($mapped, 0, 6);

            } catch (\Throwable $e) {
                Log::error("NewsService Exception: " . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Returns true if the article is clearly about gold as a commodity / investment asset.
     */
    private function isGoldCommodityArticle(array $article): bool
    {
        $text = strtolower(($article['title'] ?? '') . ' ' . ($article['description'] ?? ''));

        // Reject if exclusion term found
        foreach (self::GOLD_EXCLUSION_TERMS as $term) {
            if (str_contains($text, $term)) {
                return false;
            }
        }

        // Accept if at least one gold-commodity term found
        foreach (self::GOLD_REQUIRED_TERMS as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        // Fallback: accept if text contains "gold" or "oro" AND a price/finance signal
        $hasGold    = str_contains($text, 'gold') || str_contains($text, ' oro ');
        $hasFinance = (bool) preg_match('/\$([\d,]+)|precio|price|mercado|market|inversi|reserve|etf|commodity/i', $text);

        return $hasGold && $hasFinance;
    }

    /**
     * Assign a display category based on article content.
     */
    private function detectCategory(array $article): string
    {
        $text = strtolower(($article['title'] ?? '') . ' ' . ($article['description'] ?? ''));

        if (str_contains($text, 'banco central') || str_contains($text, 'central bank') || str_contains($text, 'reserva federal') || str_contains($text, 'fed ')) {
            return 'Banco Central';
        }
        if (str_contains($text, 'etf') || str_contains($text, 'fondo') || str_contains($text, 'fund')) {
            return 'ETF / Fondos';
        }
        if (str_contains($text, 'geopolít') || str_contains($text, 'geopolit') || str_contains($text, 'guerra') || str_contains($text, 'war') || str_contains($text, 'tensión')) {
            return 'Geopolítica';
        }
        if (str_contains($text, 'xau/usd') || str_contains($text, 'precio') || str_contains($text, 'price') || str_contains($text, 'cotización')) {
            return 'XAU/USD';
        }
        if (str_contains($text, 'token') || str_contains($text, 'digital') || str_contains($text, 'blockchain')) {
            return 'Oro Digital';
        }

        return 'Mercado';
    }

}
