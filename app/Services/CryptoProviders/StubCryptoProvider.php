<?php

declare(strict_types=1);

namespace App\Services\CryptoProviders;

use App\Contracts\CryptoProviderInterface;
use App\DTOs\CryptoInvoiceDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Stub crypto provider for local development and testing.
 * Generates fake invoices with deterministic addresses.
 */
final class StubCryptoProvider implements CryptoProviderInterface
{
    private const NETWORK_MAP = [
        'USDT' => 'TRC20',
        'BTC'  => 'Bitcoin',
        'ETH'  => 'ERC20',
    ];

    public function createInvoice(string $userId, float $amount, string $currency): CryptoInvoiceDTO
    {
        $invoiceId = 'INV-' . Str::random(16);

        return new CryptoInvoiceDTO(
            invoiceId: $invoiceId,
            address:   $this->generateFakeAddress($currency),
            currency:  $currency,
            network:   self::NETWORK_MAP[$currency] ?? null,
            qrCodeUrl: null,
            expiresAt: Carbon::now()->addHours(24),
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.crypto.webhook_secret', '');

        return hash_equals(
            hash_hmac('sha256', $payload, $secret),
            $signature,
        );
    }

    private function generateFakeAddress(string $currency): string
    {
        return match ($currency) {
            'BTC'   => '1' . Str::random(33),
            'ETH'   => '0x' . Str::random(40),
            default => 'T' . Str::random(33), // TRC20 style
        };
    }
}
