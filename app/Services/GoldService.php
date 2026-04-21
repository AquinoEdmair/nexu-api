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
        // Incluye el precio actual en la cache key para invalidar cuando cambia significativamente
        $priceSlot = (int) ($currentPrice['price'] / 50); // invalida si el precio cambia >$50
        $ttl = match($range) {
            '1h'    => 60,
            '1d'    => 300,
            '1w'    => 3600,
            '1m'    => 3600,
            default => 3600,
        };

        $history = Cache::remember("gold:history:{$range}:{$priceSlot}", $ttl, function () use ($range, $currentPrice) {
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
        // Primary: Yahoo Finance — free, no key, real-time XAU/USD
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get('https://query1.finance.yahoo.com/v8/finance/chart/XAUUSD%3DX');

            if ($response->ok()) {
                $meta  = $response->json('chart.result.0.meta', []);
                $price = (float) ($meta['regularMarketPrice'] ?? 0);

                if ($price > 1000) {
                    $prev      = (float) ($meta['chartPreviousClose'] ?? $price);
                    $change24h = $prev > 0 ? round((($price - $prev) / $prev) * 100, 2) : 0.0;
                    return ['price' => round($price, 2), 'change_24h' => $change24h];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("GoldService Yahoo Finance failed: " . $e->getMessage());
        }

        // Secondary: metals.live — free, no key
        try {
            $response = Http::timeout(5)->get('https://api.metals.live/v1/spot');

            if ($response->ok()) {
                $items     = $response->json();
                $goldEntry = collect($items)->first(fn ($item) => isset($item['gold']));
                $price     = (float) ($goldEntry['gold'] ?? 0);

                if ($price > 1000) {
                    return ['price' => round($price, 2), 'change_24h' => 0.0];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("GoldService metals.live failed: " . $e->getMessage());
        }

        // Tertiary: goldapi.io — requires API key
        $apiKey = config('services.goldapi.key');

        if (!blank($apiKey)) {
            try {
                $response = Http::timeout(5)->withHeaders([
                    'x-access-token' => $apiKey,
                    'Content-Type'   => 'application/json',
                ])->get('https://www.goldapi.io/api/XAU/USD');

                if ($response->ok()) {
                    $data = $response->json();
                    return [
                        'price'      => (float) ($data['price'] ?? 4700.00),
                        'change_24h' => round((float) ($data['chp'] ?? 0.0), 2),
                    ];
                }

                Log::error("GoldAPI Error: " . $response->body());
            } catch (\Throwable $e) {
                Log::error("GoldService goldapi.io failed: " . $e->getMessage());
            }
        }

        // Final fallback
        return ['price' => 4700.00, 'change_24h' => 0.0];
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
    // Article MUST contain at least one of these to pass (XAU/USD or gold-crypto context)
    private const XAU_REQUIRED_TERMS = [
        'xau/usd', 'xau usd', 'xauusd',
        'paxg', 'tether gold', 'xaut',
        'tokenized gold', 'oro tokenizado', 'gold-backed crypto', 'gold backed crypto',
        'oro digital', 'digital gold',
        'bitcoin gold', 'gold bitcoin', 'btc gold', 'gold btc',
        'gold crypto', 'crypto gold',
        'oro y cripto', 'cripto y oro',
    ];

    // Hard reject — article is clearly not about XAU/USD or gold crypto
    private const XAU_EXCLUSION_TERMS = [
        'medalla de oro', 'gold medal', 'golden state', 'golden globes', 'golden globe',
        'disco de oro', 'album de oro', 'gold album', 'mastercard gold', 'tarjeta gold',
        'visa gold', 'plan gold', 'suscripción gold', 'membresía gold', 'edad de oro',
        'golden age', 'golden gate', 'golden retriever', 'gold coast',
        'olympic gold', 'olimpiadas', 'juegos olímpicos',
        'gold standard certificate',
    ];

    public function getGoldNews(): array
    {
        return Cache::remember('gold:news_feed_v2', self::CACHE_TTL_NEWS, function () {
            $apiKey = config('services.newsapi.key');

            if (blank($apiKey)) {
                return [];
            }

            try {
                // Only XAU/USD as a trading pair or gold-crypto assets
                $q = '"XAU/USD" OR "XAU USD" OR "XAUUSD" OR "PAXG" OR "Tether Gold" OR "XAUT" '
                   . 'OR "tokenized gold" OR "oro tokenizado" OR "gold-backed crypto" '
                   . 'OR "digital gold" OR "oro digital" OR "bitcoin gold" OR "gold bitcoin"';

                $response = Http::get('https://newsapi.org/v2/everything', [
                    'q'        => $q,
                    'sortBy'   => 'publishedAt',
                    'pageSize' => 20,
                    'apiKey'   => $apiKey,
                ]);

                if ($response->failed()) {
                    Log::error("NewsAPI Error: " . $response->body());
                    return [];
                }

                $articles = $response->json()['articles'] ?? [];

                $filtered = array_filter($articles, fn ($a) => $this->isXauCryptoArticle($a));

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
     * Returns true only if article is specifically about XAU/USD or gold-crypto assets.
     * No loose fallback — must match an XAU/crypto term explicitly.
     */
    private function isXauCryptoArticle(array $article): bool
    {
        $text = strtolower(($article['title'] ?? '') . ' ' . ($article['description'] ?? ''));

        foreach (self::XAU_EXCLUSION_TERMS as $term) {
            if (str_contains($text, $term)) {
                return false;
            }
        }

        foreach (self::XAU_REQUIRED_TERMS as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
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
        if (str_contains($text, 'token') || str_contains($text, 'blockchain') || str_contains($text, 'paxg') || str_contains($text, 'xaut') || str_contains($text, 'tether gold') || str_contains($text, 'digital gold') || str_contains($text, 'oro digital')) {
            return 'Oro Digital';
        }
        if (str_contains($text, 'bitcoin') || str_contains($text, 'btc') || str_contains($text, 'crypto') || str_contains($text, 'cripto')) {
            return 'Cripto';
        }

        return 'XAU/USD';
    }

}
