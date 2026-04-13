<?php

declare(strict_types=1);

namespace App\Services\CryptoProviders;

use App\Contracts\CryptoProviderInterface;
use App\DTOs\CryptoInvoiceDTO;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class NowPaymentsCryptoProvider implements CryptoProviderInterface
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.crypto.nowpayments.api_key', '');
        $sandbox = config('services.crypto.nowpayments.sandbox', true);
        
        $this->baseUrl = $sandbox 
            ? 'https://api-sandbox.nowpayments.io/v1' 
            : 'https://api.nowpayments.io/v1';
    }

    public function createInvoice(string $userId, float $amount, string $currency): CryptoInvoiceDTO
    {
        $payCurrency = $this->mapCurrency($currency);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/payment", [
            'price_amount' => $amount,
            'price_currency' => 'usd',
            'pay_currency' => $payCurrency,
            'order_id' => $userId . '-' . time(),
            'order_description' => 'Deposit to NEXU Vault',
            'case' => config('services.crypto.nowpayments.sandbox', true) ? 'success' : null,
        ]);

        if ($response->failed()) {
            Log::error('NowPayments API Error', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new RuntimeException('Failed to generate deposit address with NowPayments.');
        }

        $data = $response->json();

        return new CryptoInvoiceDTO(
            invoiceId: (string) $data['payment_id'],
            address:   $data['pay_address'],
            currency:  $currency,
            network:   $this->getNetwork($currency),
            qrCodeUrl: 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($currency . ':' . $data['pay_address']),
            expiresAt: Carbon::now()->addHours(24),
            payAmount: isset($data['pay_amount']) ? (string) $data['pay_amount'] : null,
        );
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = config('services.crypto.webhook_secret', '');

        $data = json_decode($payload, true);
        if (! $data) {
            return false;
        }

        ksort($data);
        $sortedPayload = json_encode($data, JSON_UNESCAPED_SLASHES);

        return hash_equals(
            hash_hmac('sha512', $sortedPayload, $secret),
            $signature,
        );
    }

    private function mapCurrency(string $currency): string
    {
        return match (strtolower($currency)) {
            'usdt' => 'usdttrc20',
            'btc'  => 'btc',
            'eth'  => 'eth',
            default => strtolower($currency),
        };
    }

    private function getNetwork(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USDT' => 'TRC20',
            'BTC'  => 'Bitcoin',
            'ETH'  => 'ERC20',
            default => 'Unknown',
        };
    }
}
