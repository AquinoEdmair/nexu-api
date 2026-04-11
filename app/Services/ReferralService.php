<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EliteTier;
use App\Models\ElitePoint;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ReferralService
{
    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Apply referral commission to the referrer after a deposit is confirmed.
     *
     * Idempotent: if a referral_commission transaction already exists for the
     * given source deposit, it returns null without modifying anything.
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
            // Idempotency guard — prevent double-apply on duplicate events.
            $exists = Transaction::where('type', 'referral_commission')
                ->where('user_id', $referral->referrer_id)
                ->whereJsonContains('metadata->source_deposit_id', $sourceDeposit->id)
                ->exists();

            if ($exists) {
                return null;
            }

            $netAmount        = (string) $sourceDeposit->net_amount;
            $commissionRate   = (string) $referral->commission_rate;
            $commissionAmount = bcmul($netAmount, $commissionRate, 8);

            // Lock referrer's wallet for the update.
            $wallet = Wallet::where('user_id', $referral->referrer_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Only create a monetary commission transaction when amount > 0.
            $commissionTx = null;
            if (bccomp($commissionAmount, '0', 8) > 0) {
                $newAvailable = bcadd((string) $wallet->balance_available, $commissionAmount, 8);
                $newTotal     = bcadd((string) $wallet->balance_total,     $commissionAmount, 8);

                $wallet->update([
                    'balance_available' => $newAvailable,
                    'balance_total'     => $newTotal,
                ]);

                $commissionTx = Transaction::create([
                    'user_id'        => $referral->referrer_id,
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
                        'source_net_amount'  => $netAmount,
                    ],
                ]);

                // Update lifetime total earned on the referral relationship.
                $referral->increment('total_earned', $commissionAmount);
            }

            // Elite points: always accrued (independent of commission amount).
            // 1 USD of net deposit = 1 point for the referrer.
            ElitePoint::create([
                'user_id'       => $referral->referrer_id,
                'points'        => $netAmount,
                'transaction_id' => $commissionTx?->id,
                'description'   => "Depósito de referido: {$sourceDeposit->id}",
            ]);

            return $commissionTx;
        });
    }

    /**
     * Aggregated summary for a user's referral dashboard.
     *
     * @return array{
     *   code: string,
     *   share_url: string,
     *   commission_rate: string,
     *   stats: array{active_count: int, inactive_count: int, total_earned: string},
     *   elite: array{points: string, tier: string, next_tier: string|null, points_to_next: string|null, progress_pct: int}
     * }
     */
    public function getSummary(User $user): array
    {
        $referrals = Referral::where('referrer_id', $user->id)->get();

        $totalEarned    = $referrals->sum('total_earned');
        $referredIds    = $referrals->pluck('referred_id');
        $activeReferreds = User::whereIn('id', $referredIds)
            ->whereHas('transactions', fn ($q) => $q->where('type', 'deposit')->where('status', 'confirmed'))
            ->count();

        $totalPoints = (float) ElitePoint::where('user_id', $user->id)->sum('points');
        $tier        = EliteTier::fromPoints($totalPoints);
        $nextTier    = $tier->next();

        $pointsToNext = $nextTier !== null
            ? max(0, $nextTier->minPoints() - $totalPoints)
            : null;

        return [
            'code'            => $user->referral_code,
            'share_url'       => sprintf((string) config('referrals.share_url_template'), $user->referral_code),
            'commission_rate' => number_format((float) ($referrals->first()?->commission_rate ?? 0), 4, '.', ''),
            'stats'           => [
                'active_count'   => $activeReferreds,
                'inactive_count' => $referrals->count() - $activeReferreds,
                'total_earned'   => number_format((float) $totalEarned, 8, '.', ''),
            ],
            'elite' => [
                'points'        => number_format($totalPoints, 2, '.', ''),
                'tier'          => $tier->value,
                'next_tier'     => $nextTier?->value,
                'points_to_next' => $pointsToNext !== null ? number_format($pointsToNext, 2, '.', '') : null,
                'progress_pct'  => $tier->progressPct($totalPoints),
            ],
        ];
    }

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

        // Batch active-status check: one query for the whole page instead of
        // one EXISTS per row. Build an O(1) lookup set from the result.
        $referredIds = collect($paginator->items())
            ->pluck('referred_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var array<string, int> $activeSet flip: uuid → 0 for O(1) isset() */
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

    /**
     * Paginated history of referral_commission transactions earned by $user.
     *
     * @return LengthAwarePaginator<array{id: string, amount: string, source_user_masked: string, created_at: string}>
     */
    public function getEarnings(User $user, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = Transaction::where('user_id', $user->id)
            ->where('type', 'referral_commission')
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);

        // Batch-load source users to avoid N+1: collect all source_user_ids
        // from metadata and do a single query before transforming the page.
        $sourceUserIds = collect($paginator->items())
            ->map(fn (Transaction $tx) => data_get($tx->metadata, 'source_user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        /** @var array<string, string> $emailMap uuid → email */
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
                'created_at'         => $tx->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Validate a referral code for the pre-registration check.
     * Returns null when the code does not exist.
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

    // ── Private helpers ──────────────────────────────────────────────────────

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
