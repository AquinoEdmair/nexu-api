<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\TransactionFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateDepositRequest;
use App\Models\User;
use App\Services\DepositService;
use App\Services\TransactionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $depositService,
        private readonly TransactionQueryService $transactionService,
    ) {}

    /**
     * Generate a deposit address via the crypto provider.
     */
    public function initiate(InitiateDepositRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $amount = (float) $request->validated('amount');
            $currency = $request->validated('currency');
            
            $invoice = $this->depositService->initiateDeposit($user, $amount, $currency);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DepositController: failure to initiate deposit', [
                'userId'   => $user->id,
                'currency' => $request->validated('currency'),
                'amount'   => $request->validated('amount'),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo crear la dirección de depósito. Por favor, inténtelo de nuevo más tarde.',
            ], 502);
        }

        return response()->json([
            'data' => [
                'invoice_id'  => $invoice->invoice_id,
                'address'     => $invoice->address,
                'currency'    => $invoice->currency,
                'network'     => $invoice->network,
                'qr_code_url' => $invoice->qr_code_url,
                'expires_at'  => $invoice->expires_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * List deposit transactions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = new TransactionFilterDTO(
            types:   ['deposit'],
            userId:  $user->id,
            perPage: min($request->integer('per_page', 20), 50),
        );

        $paginated = $this->transactionService->list($filters);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * List pending (active) invoices for the authenticated user.
     */
    public function pending(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invoices = $this->depositService->getPendingInvoices($user);

        return response()->json([
            'data' => $invoices->map(fn ($invoice) => [
                'invoice_id'  => $invoice->invoice_id,
                'address'     => $invoice->address,
                'currency'    => $invoice->currency,
                'network'     => $invoice->network,
                'qr_code_url' => $invoice->qr_code_url,
                'status'      => $invoice->status,
                'expires_at'  => $invoice->expires_at->toIso8601String(),
                'created_at'  => $invoice->created_at->toIso8601String(),
            ]),
        ]);
    }
    /**
     * List all deposit invoices for the user.
     */
    public function invoices(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invoices = $this->depositService->getInvoiceHistory($user);

        return response()->json([
            'data' => $invoices->map(fn ($invoice) => [
                'id'              => $invoice->id,
                'invoice_id'      => $invoice->invoice_id,
                'address'         => $invoice->address,
                'currency'        => $invoice->currency,
                'network'         => $invoice->network,
                'qr_code_url'     => $invoice->qr_code_url,
                'status'          => $invoice->status,
                'amount_expected' => (string) $invoice->amount_expected,
                'amount_received' => (string) $invoice->amount_received,
                'expires_at'      => $invoice->expires_at->toIso8601String(),
                'created_at'      => $invoice->created_at->toIso8601String(),
            ]),
        ]);
    }
}
