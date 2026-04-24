<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, ?string $ip = null): bool
    {
        $secretKey = config('services.turnstile.secret_key');

        // If no secret key configured, skip verification in development
        if (blank($secretKey)) {
            Log::warning('TurnstileService: TURNSTILE_SECRET_KEY not set, skipping verification.');
            return true;
        }

        try {
            $payload = ['secret' => $secretKey, 'response' => $token];
            if ($ip) {
                $payload['remoteip'] = $ip;
            }

            $response = Http::timeout(5)->asForm()->post(self::VERIFY_URL, $payload);

            if ($response->ok()) {
                return (bool) ($response->json('success') ?? false);
            }

            Log::error('TurnstileService: verification request failed', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('TurnstileService: exception during verification', ['error' => $e->getMessage()]);
        }

        return false;
    }
}
