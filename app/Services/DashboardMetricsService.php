<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\DailyVolumeDTO;
use App\DTOs\FinancialSummaryDTO;
use App\DTOs\MetricsOverviewDTO;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Models\YieldLog;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class DashboardMetricsService
{
    // ── TTLs (seconds) ────────────────────────────────────────────────────────

    private const TTL_VOLATILE    = 30;   // retiros pendientes
    private const TTL_KPI         = 60;   // KPIs principales
    private const TTL_CHART       = 300;  // gráficas y métricas financieras
    private const TTL_TOP         = 600;  // top referidores

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * 4 KPIs principales del dashboard.
     * Cada stat tiene su propio TTL y clave de caché.
     */
    public function getOverview(): MetricsOverviewDTO
    {
        $activeUsers = (int) $this->cached(
            'overview:active_users',
            self::TTL_KPI,
            fn () => User::active()->count(),
        );

        $depositsToday = (float) $this->cached(
            'overview:deposits_today',
            self::TTL_KPI,
            fn () => Transaction::where('type', 'deposit')
                ->where('status', 'confirmed')
                ->whereDate('created_at', today())
                ->sum('net_amount'),
        );

        $pendingWithdrawals = (int) $this->cached(
            'overview:pending_withdrawals',
            self::TTL_VOLATILE,
            fn () => WithdrawalRequest::where('status', 'pending')->count(),
        );

        $systemBalance = (float) $this->cached(
            'overview:system_balance',
            self::TTL_KPI,
            fn () => Wallet::sum('balance_total'),
        );

        return new MetricsOverviewDTO(
            activeUsers:        $activeUsers,
            depositsToday:      $depositsToday,
            pendingWithdrawals: $pendingWithdrawals,
            systemBalanceTotal: $systemBalance,
        );
    }

    /**
     * Totales financieros históricos — solo para super_admin.
     */
    public function getFinancialSummary(): FinancialSummaryDTO
    {
        $zero = new FinancialSummaryDTO(0.0, 0.0, 0.0, 0.0, 0.0, 0);

        return $this->cached('financial_summary', self::TTL_CHART, function (): FinancialSummaryDTO {
            $sums = Transaction::where('status', 'confirmed')
                ->selectRaw("
                    SUM(CASE WHEN type = 'deposit'              THEN net_amount ELSE 0 END) AS total_deposited,
                    SUM(CASE WHEN type = 'withdrawal'           THEN net_amount ELSE 0 END) AS total_withdrawn,
                    SUM(CASE WHEN type = 'yield'                THEN net_amount ELSE 0 END) AS total_yield,
                    SUM(CASE WHEN type = 'commission'           THEN net_amount ELSE 0 END) AS total_commissions,
                    SUM(CASE WHEN type = 'referral_commission'  THEN net_amount ELSE 0 END) AS total_referral_commissions
                ")
                ->first();

            $usersWithBalance = (int) Wallet::where('balance_total', '>', 0)->count();

            return new FinancialSummaryDTO(
                totalDeposited:           (float) ($sums?->total_deposited ?? 0),
                totalWithdrawn:           (float) ($sums?->total_withdrawn ?? 0),
                totalYieldApplied:        (float) ($sums?->total_yield ?? 0),
                totalCommissions:         (float) ($sums?->total_commissions ?? 0),
                totalReferralCommissions: (float) ($sums?->total_referral_commissions ?? 0),
                usersWithBalance:         $usersWithBalance,
            );
        }, $zero);
    }

    /**
     * Volumen de depósitos por día para los últimos N días.
     *
     * @return array<DailyVolumeDTO>
     */
    public function getDailyDepositVolume(int $days = 30): array
    {
        return $this->cached("deposit_volume:{$days}", self::TTL_CHART, function () use ($days): array {
            return $this->buildDailyVolume('deposit', $days);
        }, []);
    }

    /**
     * Volumen de retiros por día para los últimos N días.
     *
     * @return array<DailyVolumeDTO>
     */
    public function getDailyWithdrawalVolume(int $days = 30): array
    {
        return $this->cached("withdrawal_volume:{$days}", self::TTL_CHART, function () use ($days): array {
            return $this->buildDailyVolume('withdrawal', $days);
        }, []);
    }

    /**
     * Registros de nuevos usuarios por día para los últimos N días.
     *
     * @return array<array{day: string, count: int}>
     */
    public function getUserGrowth(int $days = 30): array
    {
        return $this->cached("user_growth:{$days}", self::TTL_CHART, function () use ($days): array {
            return User::where('created_at', '>=', now()->subDays($days))
                ->selectRaw('DATE(created_at) AS day, COUNT(*) AS count')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('day')
                ->get()
                ->map(fn ($row): array => [
                    'day'   => $row->day,
                    'count' => (int) $row->count,
                ])
                ->all();
        }, []);
    }

    /**
     * Últimos N yield_logs completados para gráfica de barras.
     *
     * @return Collection<int, YieldLog>
     */
    public function getYieldHistory(int $limit = 10): Collection
    {
        return $this->cached("yield_history:{$limit}", self::TTL_CHART, function () use ($limit): Collection {
            return YieldLog::where('status', 'completed')
                ->orderByDesc('applied_at')
                ->limit($limit)
                ->select('id', 'value', 'type', 'total_applied', 'users_count', 'applied_at')
                ->get();
        }, collect());
    }

    /**
     * Los N retiros pendientes más antiguos para la tabla compacta.
     *
     * @return Collection<int, WithdrawalRequest>
     */
    public function getPendingWithdrawals(int $limit = 5): Collection
    {
        return $this->cached("pending_withdrawals:{$limit}", self::TTL_VOLATILE, function () use ($limit): Collection {
            return WithdrawalRequest::where('status', 'pending')
                ->with('user:id,name,email')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();
        }, collect());
    }

    /**
     * Top N referidores por ganancias totales.
     *
     * @return Collection<int, Referral>
     */
    public function getTopReferrers(int $limit = 5): Collection
    {
        return $this->cached("top_referrers:{$limit}", self::TTL_TOP, function () use ($limit): Collection {
            return Referral::selectRaw('referrer_id, COUNT(*) AS referral_count, SUM(total_earned) AS total_earned')
                ->groupBy('referrer_id')
                ->orderByDesc('total_earned')
                ->limit($limit)
                ->with('referrer:id,name,email')
                ->get();
        }, collect());
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Generic cache wrapper with silent Redis fallback.
     * If Redis is unavailable, executes $compute directly without caching.
     * If $compute also fails, returns $default.
     *
     * @template T
     * @param  Closure(): T  $compute
     * @param  T             $default  Returned only when both cache and DB are unavailable.
     * @return T
     */
    private function cached(string $metric, int $ttl, Closure $compute, mixed $default = 0): mixed
    {
        try {
            return Cache::remember($this->cacheKey($metric), $ttl, $compute);
        } catch (\Throwable) {
            // Redis unavailable — fall back to DB directly
            try {
                return $compute();
            } catch (\Throwable) {
                return $default;
            }
        }
    }

    private function cacheKey(string $metric): string
    {
        return "dashboard:{$metric}";
    }

    /**
     * @return array<DailyVolumeDTO>
     */
    private function buildDailyVolume(string $type, int $days): array
    {
        return Transaction::where('type', $type)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) AS day, SUM(net_amount) AS total, COUNT(*) AS count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): DailyVolumeDTO => new DailyVolumeDTO(
                day:   $row->day,
                total: (float) $row->total,
                count: (int) $row->count,
            ))
            ->all();
    }
}
