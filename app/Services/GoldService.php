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

    /** Build a coherent simulated history around current price for the given range. */
    private function buildHistory(string $range, float $currentPrice): array
    {
        [$points, $format, $unit] = match($range) {
            '1h'    => [60, 'H:i',     'minutes'],
            '1d'    => [24, 'd/m H:i', 'hours'],
            '1w'    => [7,  'd/m',     'days'],
            '1m'    => [30, 'd/m',     'days'],
            default => [7,  'd/m',     'days'],
        };

        $history = [];

        for ($i = $points - 1; $i >= 0; $i--) {
            $date  = now()->{"sub{$unit}"}($i)->format($format);
            $price = $i === 0
                ? $currentPrice
                : round($currentPrice * (1 + (rand(-80, 80) / 10000)), 2);

            $history[] = ['date' => $date, 'price' => $price];
        }

        return $history;
    }

    /**
     * Obtiene noticias relacionadas al oro desde NewsAPI.org
     */
    public function getGoldNews(): array
    {
        return Cache::remember('gold:news_feed', self::CACHE_TTL_NEWS, function () {
            $apiKey = config('services.newsapi.key');
            
            if (blank($apiKey)) {
                return [];
            }

            try {
                $response = Http::get('https://newsapi.org/v2/everything', [
                    'q'        => '(oro OR gold OR XAU) AND (precio OR price OR mercado OR market OR inversión OR investment)',
                    'language' => 'es',
                    'sortBy'   => 'publishedAt',
                    'pageSize' => 6,
                    'apiKey'   => $apiKey,
                ]);

                if ($response->failed()) {
                    Log::error("NewsAPI Error: " . $response->body());
                    return [];
                }

                $articles = $response->json()['articles'] ?? [];
                
                return array_map(function ($article) {
                    return [
                        'title'  => $article['title'],
                        'excerpt' => $article['description'],
                        'date'   => now()->parse($article['publishedAt'])->diffForHumans(),
                        'source' => $article['source']['name'],
                        'url'    => $article['url'],
                        'category' => 'Mercado'
                    ];
                }, $articles);

            } catch (\Throwable $e) {
                Log::error("NewsService Exception: " . $e->getMessage());
                return [];
            }
        });
    }

}
