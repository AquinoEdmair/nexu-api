<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RecalculateEliteTierJob;
use App\Models\ElitePoint;
use App\Models\EliteTier;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ReferralService
{
    // ── Deposit commission ────────────────────────────────────────────────────

    /**
     * Apply referral commission to the referrer after a deposit is confirmed.
     * Uses the referrer's current tier rates (first vs. recurring deposit).
     * Also awards Elite points to the referrer based on the commission earned.
     *
     * Idempotent: if a referral_commission already exists for this source
     * deposit, returns null without modifying anything.
     *
     * @throws \Throwable
     */
    public function applyDepositCommission(Transaction $sourceDeposit): ?Transaction
    {
        $referral = Referral::where('referred_id', $sourceDeposit->user_id)->first();

        if ($referral === null) {
            return null;
        }

        return DB::transaction(function () use ($referral, $sourceDeposit): ?Transaction {
            // Idempotency guard.
            $exists = Transaction::where('type', 'referral_commission')
                ->where('user_id', $referral->referrer_id)
                ->whereJsonContains('metadata->source_deposit_id', $sourceDeposit->id)
                ->exists();

            if ($exists) {
                return null;
            }

            // Load referrer with tier (lock row for the wallet update below).
            $referrer = User::with('eliteTier')
                ->where('id', $referral->referrer_id)
                ->firstOrFail();

            /** @var EliteTier|null $tier */
            $tier = $referrer->eliteTier;

            // No tier assigned → no commission, no points.
            if ($tier === null) {
                return null;
            }

            // Determine first vs. recurring deposit.
            $isFirstDeposit = $referral->first_deposit_tx_id === null;
            $depositType    = $isFirstDeposit ? 'first' : 'recurring';
            $commissionRate = $isFirstDeposit
                ? (string) $tier->first_deposit_commission_rate
                : (string) $tier->recurring_commission_rate;

            if (bccomp($commissionRate, '0', 4) === 0) {
                // Rate is zero — mark first deposit if applicable, then exit.
                if ($isFirstDeposit) {
                    $referral->update(['first_deposit_tx_id' => $sourceDeposit->id]);
                }
                return null;
            }

            $netAmount        = (string) $sourceDeposit->net_amount;
            $commissionAmount = bcmul($netAmount, $commissionRate, 8);

            // Lock referrer wallet.
            $wallet = Wallet::where('user_id', $referrer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newInOperation = bcadd((string) $wallet->balance_in_operation, $commissionAmount, 8);

            $wallet->update([
                'balance_in_operation' => $newInOperation,
                'balance_total'        => $newInOperation,
            ]);

            $commissionTx = Transaction::create([
                'user_id'        => $referrer->id,
                'type'           => 'referral_commission',
                'amount'         => $commissionAmount,
                'fee_amount'     => '0.00000000',
                'net_amount'     => $commissionAmount,
                'currency'       => $sourceDeposit->currency,
                'status'         => 'confirmed',
                'reference_type' => 'deposit',
                'reference_id'   => $sourceDeposit->id,
                'metadata'       => [
                    'source_deposit_id'  => $sourceDeposit->id,
                    'source_user_id'     => $sourceDeposit->user_id,
                    'commission_rate'    => $commissionRate,
                    'deposit_type'       => $depositType,
                    'tier_slug'          => $tier->slug,
                    'source_net_amount'  => $netAmount,
                ],
            ]);

            // Update lifetime earned and mark first deposit if applicable.
            $referral->increment('total_earned', $commissionAmount);

            if ($isFirstDeposit) {
                $referral->update(['first_deposit_tx_id' => $sourceDeposit->id]);
            }

            // Award Elite points to the referrer based on commission earned × multiplier.
            $this->creditPoints(
                userId:        $referrer->id,
                amount:        $commissionAmount,
                multiplier:    (string) $tier->multiplier,
                transactionId: $commissionTx->id,
                description:   "referral_commission:{$commissionTx->id}",
            );

            return $commissionTx;
        });
    }

    // ── Points for depositor ──────────────────────────────────────────────────

    /**
     * Award Elite points to the user who made a deposit.
     * Points = net_amount × user's current tier multiplier (1.0 if no tier).
     */
    public function awardPointsForDeposit(Transaction $depositTx): ?ElitePoint
    {
        $user = User::with('eliteTier')->findOrFail($depositTx->user_id);

        $multiplier = $user->eliteTier !== null
            ? (string) $user->eliteTier->multiplier
            : '1.00';

        $point = $this->creditPoints(
            userId:        $user->id,
            amount:        (string) $depositTx->net_amount,
            multiplier:    $multiplier,
            transactionId: $depositTx->id,
            description:   "deposit:{$depositTx->id}",
        );

        RecalculateEliteTierJob::dispatch($user->id);

        return $point;
    }

    // ── Points for yield ─────────────────────────────────────────────────────

    /**
     * Award Elite points to a user for a yield transaction.
     * Points = net_amount × user's current tier multiplier (1.0 if no tier).
     */
    public function awardPointsForYield(Transaction $yieldTx, string $yieldLogId): ?ElitePoint
    {
        $user = User::with('eliteTier')->findOrFail($yieldTx->user_id);

        $multiplier = $user->eliteTier !== null
            ? (string) $user->eliteTier->multiplier
            : '1.00';

        $point = $this->creditPoints(
            userId:        $user->id,
            amount:        (string) $yieldTx->net_amount,
            multiplier:    $multiplier,
            transactionId: $yieldTx->id,
            description:   "yield:{$yieldLogId}",
        );

        RecalculateEliteTierJob::dispatch($user->id);

        return $point;
    }

    // ── Points history ────────────────────────────────────────────────────────

    /**
     * Paginated Elite points history for a user with human-readable source labels.
     *
     * @return LengthAwarePaginator<array{id: string, points: string, source: string, amount_usd: string, created_at: string}>
     */
    public function getPointsHistory(User $user, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return ElitePoint::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page)
            ->through(function (ElitePoint $point): array {
                [$source, $amountUsd] = $this->resolvePointSource($point);

                return [
                    'id'         => $point->id,
                    'points'     => number_format((float) $point->points, 2, '.', ''),
                    'source'     => $source,
                    'amount_usd' => $amountUsd,
                    'created_at' => $point->created_at->toIso8601String(),
                ];
            });
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    /**
     * Aggregated referral + Elite summary for the user's dashboard.
     *
     * @return array<string, mixed>
     */
    public function getSummary(User $user): array
    {
        $user->loadMissing('eliteTier');

        $referrals = Referral::where('referrer_id', $user->id)->get();

        $totalEarned     = $referrals->sum('total_earned');
        $referredIds     = $referrals->pluck('referred_id');
        $activeReferreds = User::whereIn('id', $referredIds)
            ->whereHas('transactions', fn ($q) => $q->where('type', 'deposit')->where('status', 'confirmed'))
            ->count();

        $totalPoints = (float) ElitePoint::where('user_id', $user->id)->sum('points');

        $totalPersonalDeposit = (float) Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')
            ->where('status', 'confirmed')
            ->sum('net_amount');

        /** @var EliteTier|null $tier */
        $tier = $user->eliteTier;
        
        $allTiers = EliteTier::active()->ordered()->get();
        
        // If user has no tier, the "next" is the first one available
        $nextTier = $tier !== null 
            ? app(EliteTierService::class)->getNextTier($tier) 
            : $allTiers->first();

        $pointsToNext = $nextTier !== null
            ? max(0, (float) $nextTier->min_points - $totalPoints)
            : null;

        $progressPct = $tier !== null ? $tier->progressPct($totalPoints) : 0;

        return [
            'code'      => $user->referral_code,
            'share_url' => sprintf((string) config('referrals.share_url_template'), $user->referral_code),
            'stats'     => [
                'active_count'           => $activeReferreds,
                'inactive_count'         => $referrals->count() - $activeReferreds,
                'total_earned'           => number_format((float) $totalEarned, 8, '.', ''),
                'total_personal_deposit' => number_format($totalPersonalDeposit, 2, '.', ''),
            ],
            'elite' => [
                'points_total' => number_format($totalPoints, 2, '.', ''),
                'tier'         => $tier !== null ? [
                    'name'                          => $tier->name,
                    'slug'                          => $tier->slug,
                    'multiplier'                    => number_format((float) $tier->multiplier, 2, '.', ''),
                    'first_deposit_commission_rate' => number_format((float) $tier->first_deposit_commission_rate, 4, '.', ''),
                    'recurring_commission_rate'     => number_format((float) $tier->recurring_commission_rate, 4, '.', ''),
                ] : null,
                'next_tier'     => $nextTier !== null ? [
                    'name'       => $nextTier->name,
                    'slug'       => $nextTier->slug,
                    'min_points' => number_format((float) $nextTier->min_points, 2, '.', ''),
                ] : null,
                'points_to_next' => $pointsToNext !== null
                    ? number_format($pointsToNext, 2, '.', '')
                    : null,
                'progress_pct' => $progressPct,
                'tiers'        => $allTiers->map(fn($t) => [
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'min_points' => (float) $t->min_points,
                ])->toArray(),
            ],
        ];
    }

    // ── Network ───────────────────────────────────────────────────────────────

    /**
     * Paginated list of users referred by $user.
     *
     * @return LengthAwarePaginator<array{id: string, masked_email: string, joined_at: string, status: string, total_generated: string}>
     */
    public function getNetwork(User $user, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $paginator = Referral::where('referrer_id', $user->id)
            ->with(['referred:id,email,created_at'])
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);

        $referredIds = collect($paginator->items())
            ->pluck('referred_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var array<string, int> $activeSet */
        $activeSet = array_flip(
            Transaction::whereIn('user_id', $referredIds)
                ->where('type', 'deposit')
                ->where('status', 'confirmed')
                ->distinct()
                ->pluck('user_id')
                ->all()
        );

        return $paginator->through(function (Referral $referral) use ($activeSet): array {
            $referred = $referral->referred;

            return [
                'id'              => $referral->id,
                'masked_email'    => $this->maskEmail($referred->email),
                'joined_at'       => $referred->created_at->toIso8601String(),
                'status'          => isset($activeSet[$referral->referred_id]) ? 'active' : 'inactive',
                'total_generated' => number_format((float) $referral->total_earned, 8, '.', ''),
            ];
        });
    }

    // ── Earnings ──────────────────────────────────────────────────────────────

    /**
     * Paginated referral_commission transactions for the user.
     *
     * @return LengthAwarePaginator<array{id: string, amount: string, source_user_masked: string, deposit_type: string, created_at: string}>
     */
    public function getEarnings(User $user, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = Transaction::where('user_id', $user->id)
            ->where('type', 'referral_commission')
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);

        $sourceUserIds = collect($paginator->items())
            ->map(fn (Transaction $tx) => data_get($tx->metadata, 'source_user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var array<string, string> $emailMap */
        $emailMap = User::whereIn('id', $sourceUserIds)
            ->pluck('email', 'id')
            ->all();

        return $paginator->through(function (Transaction $tx) use ($emailMap): array {
            $sourceUserId = data_get($tx->metadata, 'source_user_id');
            $sourceMasked = isset($emailMap[$sourceUserId])
                ? $this->maskEmail($emailMap[$sourceUserId])
                : '—';

            return [
                'id'                 => $tx->id,
                'amount'             => number_format((float) $tx->net_amount, 8, '.', ''),
                'source_user_masked' => $sourceMasked,
                'deposit_type'       => data_get($tx->metadata, 'deposit_type', 'recurring'),
                'created_at'         => $tx->created_at->toIso8601String(),
            ];
        });
    }

    // ── Code validation ───────────────────────────────────────────────────────

    /**
     * Validate a referral code for the pre-registration check.
     *
     * @return array{valid: bool, referrer_name: string|null}
     */
    public function validateCode(string $code): array
    {
        $user = User::where('referral_code', strtoupper(trim($code)))->first();

        if ($user === null) {
            return ['valid' => false, 'referrer_name' => null];
        }

        return [
            'valid'         => true,
            'referrer_name' => $this->maskName($user->name),
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Insert one ElitePoint row.
     * points = amount × multiplier (both as BC strings for precision).
     */
    private function creditPoints(
        string  $userId,
        string  $amount,
        string  $multiplier,
        ?string $transactionId,
        string  $description,
    ): ElitePoint {
        $points = bcmul($amount, $multiplier, 2);

        return ElitePoint::create([
            'user_id'        => $userId,
            'points'         => $points,
            'transaction_id' => $transactionId,
            'description'    => $description,
        ]);
    }

    /**
     * Parse description to derive a human-readable source label and the
     * originating USD amount from the linked transaction.
     *
     * @return array{string, string}  [source_label, amount_usd]
     */
    private function resolvePointSource(ElitePoint $point): array
    {
        $desc = $point->description ?? '';

        if (str_starts_with($desc, 'deposit:')) {
            return [__('depósito'), number_format((float) ($point->transaction?->net_amount ?? 0), 2, '.', '')];
        }

        if (str_starts_with($desc, 'yield:')) {
            return [__('rendimiento'), number_format((float) ($point->transaction?->net_amount ?? 0), 2, '.', '')];
        }

        if (str_starts_with($desc, 'referral_commission:')) {
            return [__('comisión_referido'), number_format((float) ($point->transaction?->net_amount ?? 0), 2, '.', '')];
        }

        if (str_starts_with($desc, 'admin:')) {
            return [__('ajuste_admin'), '0.00'];
        }

        return [__('otro'), '0.00'];
    }

    /** "johndoe@example.com" → "jo***@example.com" */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $masked = substr($local, 0, min(2, strlen($local))) . '***';

        return "{$masked}@{$domain}";
    }

    /** "John Doe" → "Jo***" */
    private function maskName(string $name): string
    {
        return substr($name, 0, min(2, strlen($name))) . '***';
    }
}
