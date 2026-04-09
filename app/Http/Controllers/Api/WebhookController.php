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
        // NowPayments fields: payment_id, payment_status, pay_amount, actually_paid
        $data = $request->validate([
            'payment_id'     => ['required'],
            'payment_status' => ['required', 'string'],
            'pay_currency'   => ['required', 'string'],
            'actually_paid'  => ['required'],
        ]);

        // Map to internal format for ProcessDepositWebhook job
        $payload = [
            'invoice_id' => (string) $data['payment_id'],
            'status'     => (string) $data['payment_status'],
            'amount'     => (string) $data['actually_paid'],
            'currency'   => (string) $data['pay_currency'],
            'tx_hash'    => (string) $data['payment_id'], // Use payment_id as fallback if no hash provided
        ];

        // Only process finished, confirmed or success (sandbox only) deposits
        if ($payload['status'] !== 'finished' && $payload['status'] !== 'confirmed' && $payload['status'] !== 'success') {
            return response()->json(['status' => 'ignored', 'received_status' => $payload['status']]);
        }

        ProcessDepositWebhook::dispatchSync($payload);

        return response()->json(['status' => 'ok']);
    }
}
