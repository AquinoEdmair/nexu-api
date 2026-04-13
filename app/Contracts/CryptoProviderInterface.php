<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\CryptoInvoiceDTO;

interface CryptoProviderInterface
{
    /**
     * Creates a payment invoice with the crypto provider.
     *
     * @throws \RuntimeException if the provider API call fails
     */
    public function createInvoice(string $userId, float $amount, string $currency): CryptoInvoiceDTO;

    /**
     * Verifies the HMAC-SHA256 signature of a webhook payload.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;

    /**
     * Returns the list of currency codes enabled for this merchant account.
     * Each entry is the provider's internal code (e.g. "btc", "usdttrc20").
     *
     * @return string[]
     * @throws \RuntimeException if the provider API call fails
     */
    public function getMerchantCurrencies(): array;
}
