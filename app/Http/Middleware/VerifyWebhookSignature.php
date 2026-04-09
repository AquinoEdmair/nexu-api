<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.crypto.webhook_secret', '');
        // NowPayments manda la firma en x-nowpayments-sig
        // y nuestro fallback puede seguir siendo X-Webhook-Signature
        $signature = $request->header('x-nowpayments-sig', $request->header('X-Webhook-Signature'));
        
        if ($secret === '') {
            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        if ($signature === null || $signature === '') {
            return response()->json(['message' => 'Missing signature.'], 403);
        }

        /** @var \App\Contracts\CryptoProviderInterface $provider */
        $provider = app(\App\Contracts\CryptoProviderInterface::class);

        // Pasamos el raw payload y la firma al proveedor para que él se haga cargo
        if (! $provider->verifyWebhookSignature($request->getContent(), $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        return $next($request);
    }
}
