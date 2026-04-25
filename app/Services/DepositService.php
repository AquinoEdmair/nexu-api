<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\DepositConfirmed;
use App\Models\Admin;
use App\Models\DepositAddress;
use App\Models\DepositCurrency;
use App\Models\DepositRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DepositService
{
    public function __construct(
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * Create a pending deposit request for the user.
     * Picks a random active address for the given currency.
     *
     * @throws \RuntimeException if no active address exists for the currency
     */
    public function create(User $user, string $currencySymbol, float $amount): DepositRequest
    {
        $address = DepositAddress::whereHas(
            'currency',
            fn ($q) => $q->where('symbol', $currencySymbol)->where('is_active', true)
        )
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if ($address === null) {
            throw new \RuntimeException("No hay direcciones activas para {$currencySymbol}.");
        }

        $currency = $address->currency;

        return DepositRequest::create([
            'user_id'            => $user->id,
            'deposit_address_id' => $address->id,
            'currency'           => $currency->symbol,
            'network'            => $currency->network,
            'address'            => $address->address,
            'qr_image_path'      => $address->qr_image_path,
            'amount_expected'    => round($amount, 8),
            'status'             => 'pending',
        ]);
    }

    /**
     * User confirms they sent the payment and provides a tx_hash.
     *
     * @throws \RuntimeException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function clientConfirm(DepositRequest $request, User $user, string $txHash): void
    {
        if ($request->user_id !== $user->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('No autorizado.');
        }

        if ($request->status !== 'pending') {
            throw new \RuntimeException('Solo se puede confirmar un depósito en estado pendiente.');
        }

        $request->update([
            'tx_hash'             => $txHash,
            'status'              => 'client_confirmed',
            'client_confirmed_at' => now(),
        ]);
    }

    /**
     * Admin approves the deposit: credits wallet and creates transaction.
     *
     * @throws \RuntimeException
     */
    public function approve(DepositRequest $depositRequest, Admin $admin): void
    {
        if ($depositRequest->status !== 'client_confirmed') {
            throw new \RuntimeException('Solo se puede aprobar un depósito confirmado por el cliente.');
        }

        $result = DB::transaction(function () use ($depositRequest, $admin): array {
            $user = User::findOrFail($depositRequest->user_id);

            $commissionRate = $this->commissionService->getActiveRate('deposit');
            $rateDecimal    = bcdiv((string) $commissionRate, '100', 10);
            $gross          = (string) $depositRequest->amount_expected;
            $divisor        = bcadd('1', $rateDecimal, 10);
            $netAmount      = bcdiv($gross, $divisor, 8);
            $feeAmount      = bcsub($gross, $netAmount, 8);

            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $newBalance = bcadd((string) $wallet->balance_in_operation, $netAmount, 8);

            $wallet->update([
                'balance_in_operation' => $newBalance,
                'balance_total'        => $newBalance,
            ]);

            $tx = Transaction::create([
                'user_id'        => $user->id,
                'wallet_id'      => $wallet->id,
                'type'           => 'deposit',
                'amount'         => $gross,
                'fee_amount'     => $feeAmount,
                'net_amount'     => $netAmount,
                'currency'       => $depositRequest->currency,
                'status'         => 'confirmed',
                'external_tx_id' => $depositRequest->tx_hash,
                'metadata'       => [
                    'deposit_request_id' => $depositRequest->id,
                    'commission_rate'    => $commissionRate,
                    'reviewed_by'        => $admin->id,
                ],
            ]);

            $depositRequest->update([
                'status'         => 'completed',
                'reviewed_by'    => $admin->id,
                'reviewed_at'    => now(),
                'transaction_id' => $tx->id,
            ]);

            return ['user' => $user, 'tx' => $tx, 'netAmount' => $netAmount, 'feeAmount' => $feeAmount];
        });

        try {
            DepositConfirmed::dispatch(
                $result['user'],
                $result['tx'],
                $result['netAmount'],
                $depositRequest->currency,
            );

            if (bccomp((string) $result['feeAmount'], '0', 8) > 0) {
                $this->commissionService->recordToLedger(
                    sourceType:  'deposit',
                    sourceId:    $result['tx']->id,
                    userId:      $result['user']->id,
                    amount:      (float) $result['feeAmount'],
                    rate:        $this->commissionService->getActiveRate('deposit'),
                    description: "Comisión depósito manual — {$depositRequest->currency}",
                );
            }
        } catch (\Throwable $e) {
            Log::error('DepositService: post-approval side effects failed', [
                'deposit_request_id' => $depositRequest->id,
                'error'              => $e->getMessage(),
            ]);
        }
    }

    /**
     * Admin (or system) cancels a deposit request.
     *
     * @throws \RuntimeException
     */
    public function cancel(DepositRequest $depositRequest, Admin $admin, ?string $reason = null): void
    {
        if ($depositRequest->status === 'completed') {
            throw new \RuntimeException('No se puede cancelar un depósito ya completado.');
        }

        $depositRequest->update([
            'status'           => 'cancelled',
            'reviewed_by'      => $admin->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Returns paginated deposit request history for a user.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<DepositRequest>
     */
    public function getHistory(User $user, int $page = 1, int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        return DepositRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Returns active deposit currencies with at least one active address.
     *
     * @return Collection<int, DepositCurrency>
     */
    public function getActiveCurrencies(): Collection
    {
        return DepositCurrency::where('is_active', true)
            ->whereHas('activeAddresses')
            ->orderBy('sort_order')
            ->orderBy('symbol')
            ->get();
    }
}
