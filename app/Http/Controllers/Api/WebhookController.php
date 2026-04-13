<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDepositWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController extends Controller
{
    /**
     * Handle deposit confirmation webhook from the crypto provider.
     * Dispatches processing to a queued Job and returns 200 immediately.
     */
    public function deposit(Request $request): JsonResponse
    {
        // NowPayments IPN fields reference:
        //   price_amount   — the amount in price_currency (USD) that was invoiced
        //   price_currency — the fiat/base currency (USD)
        //   pay_currency   — the crypto the user actually paid in (BTC, ETH, etc.)
        //   actually_paid  — how much crypto was received (pay_currency units, NOT USD)
        $data = $request->validate([
            'payment_id'     => ['required'],
            'payment_status' => ['required', 'string'],
            'price_amount'   => ['required', 'numeric'],
            'price_currency' => ['required', 'string'],
            'pay_currency'   => ['required', 'string'],
            'actually_paid'  => ['required', 'numeric'],
        ]);

        // Always credit the user in USD (price_amount), never in crypto units.
        // actually_paid is stored in metadata for reference only.
        $payload = [
            'invoice_id'    => (string) $data['payment_id'],
            'status'        => (string) $data['payment_status'],
            'amount'        => (string) $data['price_amount'],   // USD
            'currency'      => strtoupper((string) $data['price_currency']), // USD
            'tx_hash'       => (string) $data['payment_id'],     // payment_id as tx reference
            'actually_paid' => (string) $data['actually_paid'],  // crypto units (for metadata)
            'pay_currency'  => strtoupper((string) $data['pay_currency']),
        ];

        // Only process finished, confirmed or success (sandbox only) deposits
        if ($payload['status'] !== 'finished' && $payload['status'] !== 'confirmed' && $payload['status'] !== 'success') {
            return response()->json(['status' => 'ignored', 'received_status' => $payload['status']]);
        }

        ProcessDepositWebhook::dispatchSync($payload);

        return response()->json(['status' => 'ok']);
    }
}
