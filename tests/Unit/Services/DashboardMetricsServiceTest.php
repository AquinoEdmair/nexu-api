<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\DailyVolumeDTO;
use App\DTOs\FinancialSummaryDTO;
use App\DTOs\MetricsOverviewDTO;
use App\Filament\Widgets\FinancialSummaryWidget;
use App\Models\Admin;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Models\YieldLog;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardMetricsService $service;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DashboardMetricsService::class);

        $this->admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin@nexu.com',
            'password' => bcrypt('password'),
            'role'     => 'super_admin',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function makeTransaction(array $attrs = []): Transaction
    {
        $user = User::factory()->create();

        return Transaction::create(array_merge([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 100.0,
            'fee_amount' => 0.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
            'created_at' => now(),
        ], $attrs));
    }

    private function makeYieldLog(array $attrs = []): YieldLog
    {
        return YieldLog::create(array_merge([
            'applied_by'    => $this->admin->id,
            'type'          => 'percentage',
            'value'         => 2.0,
            'status'        => 'completed',
            'total_applied' => 100.0,
            'users_count'   => 5,
            'applied_at'    => now(),
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. getOverview
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_overview_returns_dto(): void
    {
        $overview = $this->service->getOverview();

        $this->assertInstanceOf(MetricsOverviewDTO::class, $overview);
    }

    /** @test */
    public function test_overview_empty_system_returns_all_zeros(): void
    {
        $overview = $this->service->getOverview();

        $this->assertSame(0, $overview->activeUsers);
        $this->assertEqualsWithDelta(0.0, $overview->depositsToday, 0.001);
        $this->assertSame(0, $overview->pendingWithdrawals);
        $this->assertEqualsWithDelta(0.0, $overview->systemBalanceTotal, 0.001);
    }

    /** @test */
    public function test_overview_counts_active_users_only(): void
    {
        User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'blocked']);
        User::factory()->create(['status' => 'pending']);

        $overview = $this->service->getOverview();

        $this->assertSame(2, $overview->activeUsers);
    }

    /** @test */
    public function test_overview_deposits_today_uses_net_amount_not_gross(): void
    {
        $user = User::factory()->create();

        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 110.0,
            'fee_amount' => 10.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
            'created_at' => now(),
        ]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(100.0, $overview->depositsToday, 0.001);
    }

    /** @test */
    public function test_overview_deposits_today_accumulates_multiple_deposits(): void
    {
        $user = User::factory()->create();

        foreach ([100.0, 200.0, 50.0] as $net) {
            Transaction::create([
                'user_id'    => $user->id,
                'type'       => 'deposit',
                'status'     => 'confirmed',
                'amount'     => $net,
                'fee_amount' => 0.0,
                'net_amount' => $net,
                'currency'   => 'USDT',
                'created_at' => now(),
            ]);
        }

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(350.0, $overview->depositsToday, 0.001);
    }

    /** @test */
    public function test_overview_deposits_today_excludes_other_days(): void
    {
        $user = User::factory()->create();

        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 50.0,
            'fee_amount' => 0.0,
            'net_amount' => 50.0,
            'currency'   => 'USDT',
            'created_at' => now()->subDays(2),
        ]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(0.0, $overview->depositsToday, 0.001);
    }

    /** @test */
    public function test_overview_deposits_today_excludes_pending_status(): void
    {
        $this->makeTransaction(['status' => 'pending', 'net_amount' => 200.0, 'created_at' => now()]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(0.0, $overview->depositsToday, 0.001);
    }

    /** @test */
    public function test_overview_deposits_today_excludes_rejected_status(): void
    {
        $this->makeTransaction(['status' => 'rejected', 'net_amount' => 150.0, 'created_at' => now()]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(0.0, $overview->depositsToday, 0.001);
    }

    /** @test */
    public function test_overview_deposits_today_excludes_non_deposit_types(): void
    {
        // A confirmed yield transaction today should not count as depositsToday
        $this->makeTransaction(['type' => 'yield', 'status' => 'confirmed', 'net_amount' => 999.0, 'created_at' => now()]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(0.0, $overview->depositsToday, 0.001);
    }

    /** @test */
    public function test_overview_counts_pending_withdrawals_only(): void
    {
        $user = User::factory()->create();

        foreach (['pending', 'pending', 'approved', 'completed', 'rejected'] as $idx => $status) {
            WithdrawalRequest::create([
                'user_id'             => $user->id,
                'amount'              => 10.0,
                'currency'            => 'USDT',
                'destination_address' => "addr_{$idx}",
                'status'              => $status,
            ]);
        }

        $overview = $this->service->getOverview();

        $this->assertSame(2, $overview->pendingWithdrawals);
    }

    /** @test */
    public function test_overview_system_balance_sums_all_wallets(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Wallet::factory()->create(['user_id' => $u1->id, 'balance_total' => 500.0]);
        Wallet::factory()->create(['user_id' => $u2->id, 'balance_total' => 300.0]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(800.0, $overview->systemBalanceTotal, 0.001);
    }

    /** @test */
    public function test_overview_system_balance_includes_zero_balance_wallets(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Wallet::factory()->create(['user_id' => $u1->id, 'balance_total' => 0.0]);
        Wallet::factory()->create(['user_id' => $u2->id, 'balance_total' => 200.0]);

        $overview = $this->service->getOverview();

        $this->assertEqualsWithDelta(200.0, $overview->systemBalanceTotal, 0.001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. getFinancialSummary
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_financial_summary_returns_dto(): void
    {
        $summary = $this->service->getFinancialSummary();

        $this->assertInstanceOf(FinancialSummaryDTO::class, $summary);
    }

    /** @test */
    public function test_financial_summary_empty_system_returns_all_zeros(): void
    {
        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(0.0, $summary->totalDeposited, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->totalWithdrawn, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->totalYieldApplied, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->totalCommissions, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->totalReferralCommissions, 0.001);
        $this->assertSame(0, $summary->usersWithBalance);
    }

    /** @test */
    public function test_financial_summary_sums_all_five_types_independently(): void
    {
        $user = User::factory()->create();

        $types = [
            ['type' => 'deposit',             'net_amount' => 1000.0],
            ['type' => 'withdrawal',          'net_amount' => 200.0],
            ['type' => 'yield',               'net_amount' => 50.0],
            ['type' => 'commission',          'net_amount' => 30.0],
            ['type' => 'referral_commission', 'net_amount' => 15.0],
        ];

        foreach ($types as $attrs) {
            Transaction::create(array_merge([
                'user_id'    => $user->id,
                'status'     => 'confirmed',
                'amount'     => $attrs['net_amount'],
                'fee_amount' => 0.0,
                'currency'   => 'USDT',
            ], $attrs));
        }

        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(1000.0, $summary->totalDeposited, 0.001);
        $this->assertEqualsWithDelta(200.0,  $summary->totalWithdrawn, 0.001);
        $this->assertEqualsWithDelta(50.0,   $summary->totalYieldApplied, 0.001);
        $this->assertEqualsWithDelta(30.0,   $summary->totalCommissions, 0.001);
        $this->assertEqualsWithDelta(15.0,   $summary->totalReferralCommissions, 0.001);
    }

    /** @test */
    public function test_financial_summary_types_are_isolated_from_each_other(): void
    {
        $user = User::factory()->create();

        // Only deposits — all other metrics must be 0
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 500.0,
            'fee_amount' => 0.0,
            'net_amount' => 500.0,
            'currency'   => 'USDT',
        ]);

        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(500.0, $summary->totalDeposited, 0.001);
        $this->assertEqualsWithDelta(0.0,   $summary->totalWithdrawn, 0.001);
        $this->assertEqualsWithDelta(0.0,   $summary->totalYieldApplied, 0.001);
        $this->assertEqualsWithDelta(0.0,   $summary->totalCommissions, 0.001);
        $this->assertEqualsWithDelta(0.0,   $summary->totalReferralCommissions, 0.001);
    }

    /** @test */
    public function test_financial_summary_excludes_pending_transactions(): void
    {
        $this->makeTransaction(['status' => 'pending', 'net_amount' => 500.0]);

        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(0.0, $summary->totalDeposited, 0.001);
    }

    /** @test */
    public function test_financial_summary_excludes_rejected_transactions(): void
    {
        $this->makeTransaction(['status' => 'rejected', 'net_amount' => 300.0]);
        // A rejected withdrawal should not count as totalWithdrawn
        $this->makeTransaction(['type' => 'withdrawal', 'status' => 'rejected', 'net_amount' => 100.0]);

        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(0.0, $summary->totalDeposited, 0.001);
        $this->assertEqualsWithDelta(0.0, $summary->totalWithdrawn, 0.001);
    }

    /** @test */
    public function test_financial_summary_total_yield_can_be_negative(): void
    {
        $user = User::factory()->create();

        // Positive yield
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'yield',
            'status'     => 'confirmed',
            'amount'     => 100.0,
            'fee_amount' => 0.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
        ]);

        // Larger negative yield
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'yield',
            'status'     => 'confirmed',
            'amount'     => -200.0,
            'fee_amount' => 0.0,
            'net_amount' => -200.0,
            'currency'   => 'USDT',
        ]);

        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(-100.0, $summary->totalYieldApplied, 0.001);
    }

    /** @test */
    public function test_financial_summary_users_with_balance_excludes_zero_wallets(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        Wallet::factory()->create(['user_id' => $u1->id, 'balance_total' => 100.0]);
        Wallet::factory()->create(['user_id' => $u2->id, 'balance_total' => 0.0]);
        Wallet::factory()->create(['user_id' => $u3->id, 'balance_total' => 50.0]);

        $summary = $this->service->getFinancialSummary();

        $this->assertSame(2, $summary->usersWithBalance);
    }

    /** @test */
    public function test_financial_summary_deposits_uses_net_not_gross(): void
    {
        $user = User::factory()->create();

        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 110.0,
            'fee_amount' => 10.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
        ]);

        $summary = $this->service->getFinancialSummary();

        // totalDeposited must reflect net_amount (100), not gross amount (110)
        $this->assertEqualsWithDelta(100.0, $summary->totalDeposited, 0.001);
    }

    /** @test */
    public function test_financial_summary_commission_counted_separately_from_deposit(): void
    {
        $user = User::factory()->create();

        // One deposit + its matching commission — both confirmed
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 110.0,
            'fee_amount' => 10.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
        ]);
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'commission',
            'status'     => 'confirmed',
            'amount'     => 10.0,
            'fee_amount' => 0.0,
            'net_amount' => 10.0,
            'currency'   => 'USDT',
        ]);

        $summary = $this->service->getFinancialSummary();

        $this->assertEqualsWithDelta(100.0, $summary->totalDeposited, 0.001);
        $this->assertEqualsWithDelta(10.0,  $summary->totalCommissions, 0.001);
        // totalDeposited + totalCommissions ≠ double-counting
        $this->assertEqualsWithDelta(110.0, $summary->totalDeposited + $summary->totalCommissions, 0.001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. getDailyDepositVolume
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_daily_deposit_volume_empty_system_returns_empty_array(): void
    {
        $result = $this->service->getDailyDepositVolume(30);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_deposit_volume_returns_dto_array(): void
    {
        $this->makeTransaction(['created_at' => today()]);

        $result = $this->service->getDailyDepositVolume(30);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertInstanceOf(DailyVolumeDTO::class, $result[0]);
    }

    /** @test */
    public function test_daily_deposit_volume_groups_by_day(): void
    {
        $user = User::factory()->create();

        foreach ([100.0, 200.0] as $net) {
            Transaction::create([
                'user_id'    => $user->id,
                'type'       => 'deposit',
                'status'     => 'confirmed',
                'amount'     => $net,
                'fee_amount' => 0.0,
                'net_amount' => $net,
                'currency'   => 'USDT',
                'created_at' => today(),
            ]);
        }

        $result = $this->service->getDailyDepositVolume(30);

        $today = array_filter($result, fn (DailyVolumeDTO $dto): bool => $dto->day === today()->toDateString());
        $todayDto = reset($today);

        $this->assertEqualsWithDelta(300.0, $todayDto->total, 0.001);
        $this->assertSame(2, $todayDto->count);
    }

    /** @test */
    public function test_daily_deposit_volume_excludes_data_outside_window(): void
    {
        $this->makeTransaction(['created_at' => now()->subDays(40)]);

        $result = $this->service->getDailyDepositVolume(30);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_deposit_volume_excludes_non_confirmed_transactions(): void
    {
        $this->makeTransaction(['status' => 'pending', 'created_at' => today()]);

        $result = $this->service->getDailyDepositVolume(30);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_deposit_volume_excludes_non_deposit_types(): void
    {
        $this->makeTransaction(['type' => 'withdrawal', 'status' => 'confirmed', 'created_at' => today()]);

        $result = $this->service->getDailyDepositVolume(30);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_deposit_volume_ordered_by_day_asc(): void
    {
        $user = User::factory()->create();

        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 50.0,
            'fee_amount' => 0.0,
            'net_amount' => 50.0,
            'currency'   => 'USDT',
            'created_at' => now()->subDays(2),
        ]);
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 100.0,
            'fee_amount' => 0.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
            'created_at' => today(),
        ]);

        $result = $this->service->getDailyDepositVolume(30);

        $this->assertCount(2, $result);
        $this->assertLessThan($result[1]->day, $result[1]->day >= $result[0]->day ? $result[1]->day : $result[0]->day);
        $this->assertTrue($result[0]->day <= $result[1]->day);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. getDailyWithdrawalVolume  (zero coverage — critical gap)
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_daily_withdrawal_volume_empty_system_returns_empty_array(): void
    {
        $result = $this->service->getDailyWithdrawalVolume(30);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_withdrawal_volume_returns_dto_array(): void
    {
        $this->makeTransaction(['type' => 'withdrawal', 'status' => 'confirmed', 'created_at' => today()]);

        $result = $this->service->getDailyWithdrawalVolume(30);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertInstanceOf(DailyVolumeDTO::class, $result[0]);
    }

    /** @test */
    public function test_daily_withdrawal_volume_groups_by_day(): void
    {
        $user = User::factory()->create();

        foreach ([50.0, 80.0] as $net) {
            Transaction::create([
                'user_id'    => $user->id,
                'type'       => 'withdrawal',
                'status'     => 'confirmed',
                'amount'     => $net,
                'fee_amount' => 0.0,
                'net_amount' => $net,
                'currency'   => 'USDT',
                'created_at' => today(),
            ]);
        }

        $result = $this->service->getDailyWithdrawalVolume(30);

        $today = array_filter($result, fn (DailyVolumeDTO $dto): bool => $dto->day === today()->toDateString());
        $todayDto = reset($today);

        $this->assertEqualsWithDelta(130.0, $todayDto->total, 0.001);
        $this->assertSame(2, $todayDto->count);
    }

    /** @test */
    public function test_daily_withdrawal_volume_excludes_data_outside_window(): void
    {
        $this->makeTransaction(['type' => 'withdrawal', 'status' => 'confirmed', 'created_at' => now()->subDays(40)]);

        $result = $this->service->getDailyWithdrawalVolume(30);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_withdrawal_volume_excludes_non_confirmed(): void
    {
        $this->makeTransaction(['type' => 'withdrawal', 'status' => 'pending', 'created_at' => today()]);

        $result = $this->service->getDailyWithdrawalVolume(30);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_daily_withdrawal_volume_excludes_deposit_transactions(): void
    {
        // Confirmed deposit today must not appear in withdrawal volume
        $this->makeTransaction(['type' => 'deposit', 'status' => 'confirmed', 'created_at' => today()]);

        $result = $this->service->getDailyWithdrawalVolume(30);

        $this->assertEmpty($result);
    }

    /** @test */
    public function test_deposit_and_withdrawal_volumes_are_independent(): void
    {
        $user = User::factory()->create();

        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 500.0,
            'fee_amount' => 0.0,
            'net_amount' => 500.0,
            'currency'   => 'USDT',
            'created_at' => today(),
        ]);
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'withdrawal',
            'status'     => 'confirmed',
            'amount'     => 100.0,
            'fee_amount' => 0.0,
            'net_amount' => 100.0,
            'currency'   => 'USDT',
            'created_at' => today(),
        ]);

        $depositVolume    = $this->service->getDailyDepositVolume(30);
        $withdrawalVolume = $this->service->getDailyWithdrawalVolume(30);

        $this->assertCount(1, $depositVolume);
        $this->assertCount(1, $withdrawalVolume);
        $this->assertEqualsWithDelta(500.0, $depositVolume[0]->total, 0.001);
        $this->assertEqualsWithDelta(100.0, $withdrawalVolume[0]->total, 0.001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. getUserGrowth
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_user_growth_empty_system_returns_empty_array(): void
    {
        $result = $this->service->getUserGrowth(30);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function test_user_growth_returns_array_of_day_count_pairs(): void
    {
        User::factory()->create(['created_at' => now()]);
        User::factory()->create(['created_at' => now()]);

        $result = $this->service->getUserGrowth(30);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('day', $result[0]);
        $this->assertArrayHasKey('count', $result[0]);
        $this->assertIsInt($result[0]['count']);
    }

    /** @test */
    public function test_user_growth_groups_multiple_users_same_day(): void
    {
        User::factory()->create(['created_at' => today()]);
        User::factory()->create(['created_at' => today()]);
        User::factory()->create(['created_at' => today()]);

        $result = $this->service->getUserGrowth(30);

        $today = array_filter($result, fn (array $row): bool => $row['day'] === today()->toDateString());
        $todayRow = reset($today);

        $this->assertSame(3, $todayRow['count']);
    }

    /** @test */
    public function test_user_growth_separates_different_days(): void
    {
        User::factory()->create(['created_at' => today()]);
        User::factory()->create(['created_at' => now()->subDay()]);

        $result = $this->service->getUserGrowth(30);

        $this->assertCount(2, $result);
        foreach ($result as $row) {
            $this->assertSame(1, $row['count']);
        }
    }

    /** @test */
    public function test_user_growth_excludes_users_outside_window(): void
    {
        User::factory()->create(['created_at' => now()->subDays(40)]);

        $result = $this->service->getUserGrowth(30);

        $this->assertEmpty($result);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. getYieldHistory
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_yield_history_empty_system_returns_empty_collection(): void
    {
        $result = $this->service->getYieldHistory(10);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function test_yield_history_returns_only_completed_yields(): void
    {
        $this->makeYieldLog(['status' => 'completed', 'total_applied' => 500.0]);
        $this->makeYieldLog(['status' => 'processing', 'total_applied' => null]);

        $result = $this->service->getYieldHistory(10);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function test_yield_history_ordered_by_applied_at_desc(): void
    {
        $this->makeYieldLog(['applied_at' => now()->subDays(5), 'total_applied' => 100.0]);
        $this->makeYieldLog(['applied_at' => now()->subDay(),   'total_applied' => 200.0]);
        $this->makeYieldLog(['applied_at' => now()->subDays(3), 'total_applied' => 300.0]);

        $result = $this->service->getYieldHistory(10);

        $this->assertCount(3, $result);
        // Most recent first
        $this->assertEqualsWithDelta(200.0, (float) $result->first()->total_applied, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $result->last()->total_applied, 0.001);
    }

    /** @test */
    public function test_yield_history_respects_limit(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->makeYieldLog(['applied_at' => now()->subDays($i)]);
        }

        $result = $this->service->getYieldHistory(10);

        $this->assertCount(10, $result);
    }

    /** @test */
    public function test_yield_history_includes_negative_total_applied(): void
    {
        $this->makeYieldLog(['total_applied' => -500.0, 'applied_at' => now()]);

        $result = $this->service->getYieldHistory(10);

        $this->assertCount(1, $result);
        $this->assertEqualsWithDelta(-500.0, (float) $result->first()->total_applied, 0.001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. getPendingWithdrawals
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_pending_withdrawals_empty_system_returns_empty_collection(): void
    {
        $result = $this->service->getPendingWithdrawals(5);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function test_pending_withdrawals_returns_oldest_first(): void
    {
        $user = User::factory()->create();

        $old = WithdrawalRequest::create([
            'user_id'             => $user->id,
            'amount'              => 100.0,
            'currency'            => 'USDT',
            'destination_address' => 'addr_old',
            'status'              => 'pending',
            'created_at'          => now()->subDays(3),
        ]);

        WithdrawalRequest::create([
            'user_id'             => $user->id,
            'amount'              => 50.0,
            'currency'            => 'USDT',
            'destination_address' => 'addr_new',
            'status'              => 'pending',
            'created_at'          => now()->subDay(),
        ]);

        $result = $this->service->getPendingWithdrawals(5);

        $this->assertSame($old->id, $result->first()->id);
    }

    /** @test */
    public function test_pending_withdrawals_respects_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 8; $i++) {
            WithdrawalRequest::create([
                'user_id'             => $user->id,
                'amount'              => 10.0,
                'currency'            => 'USDT',
                'destination_address' => "addr_{$i}",
                'status'              => 'pending',
            ]);
        }

        $result = $this->service->getPendingWithdrawals(5);

        $this->assertCount(5, $result);
    }

    /** @test */
    public function test_pending_withdrawals_excludes_non_pending_statuses(): void
    {
        $user = User::factory()->create();

        foreach (['approved', 'completed', 'rejected', 'processing'] as $idx => $status) {
            WithdrawalRequest::create([
                'user_id'             => $user->id,
                'amount'              => 10.0,
                'currency'            => 'USDT',
                'destination_address' => "addr_{$idx}",
                'status'              => $status,
            ]);
        }

        $result = $this->service->getPendingWithdrawals(5);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function test_pending_withdrawals_eager_loads_user_relation(): void
    {
        $user = User::factory()->create(['name' => 'Test User']);

        WithdrawalRequest::create([
            'user_id'             => $user->id,
            'amount'              => 50.0,
            'currency'            => 'USDT',
            'destination_address' => 'addr',
            'status'              => 'pending',
        ]);

        $result = $this->service->getPendingWithdrawals(5);

        // Relation must be loaded — accessing user must not fire an additional query
        $this->assertTrue($result->first()->relationLoaded('user'));
        $this->assertSame('Test User', $result->first()->user->name);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. getTopReferrers
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_top_referrers_empty_system_returns_empty_collection(): void
    {
        $result = $this->service->getTopReferrers(5);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function test_top_referrers_ordered_by_total_earnings_desc(): void
    {
        $referrer1 = User::factory()->create();
        $referrer2 = User::factory()->create();
        $referred1 = User::factory()->create();
        $referred2 = User::factory()->create();

        Referral::create(['referrer_id' => $referrer1->id, 'referred_id' => $referred1->id, 'commission_rate' => 0.05, 'total_earned' => 100.0]);
        Referral::create(['referrer_id' => $referrer2->id, 'referred_id' => $referred2->id, 'commission_rate' => 0.05, 'total_earned' => 500.0]);

        $result = $this->service->getTopReferrers(5);

        $this->assertSame($referrer2->id, $result->first()->referrer_id);
    }

    /** @test */
    public function test_top_referrers_aggregates_multiple_referrals_per_referrer(): void
    {
        $referrer = User::factory()->create();

        foreach ([100.0, 200.0, 300.0] as $earned) {
            Referral::create([
                'referrer_id'   => $referrer->id,
                'referred_id'   => User::factory()->create()->id,
                'commission_rate' => 0.05,
                'total_earned'  => $earned,
            ]);
        }

        $result = $this->service->getTopReferrers(5);

        $this->assertCount(1, $result); // one referrer
        $this->assertEqualsWithDelta(600.0, (float) $result->first()->total_earned, 0.001);
        $this->assertSame(3, (int) $result->first()->referral_count);
    }

    /** @test */
    public function test_top_referrers_respects_limit(): void
    {
        for ($i = 0; $i < 8; $i++) {
            Referral::create([
                'referrer_id'     => User::factory()->create()->id,
                'referred_id'     => User::factory()->create()->id,
                'commission_rate' => 0.05,
                'total_earned'    => rand(1, 100),
            ]);
        }

        $result = $this->service->getTopReferrers(5);

        $this->assertCount(5, $result);
    }

    /** @test */
    public function test_top_referrers_eager_loads_referrer_relation(): void
    {
        $referrer = User::factory()->create(['name' => 'Top Earner']);

        Referral::create([
            'referrer_id'     => $referrer->id,
            'referred_id'     => User::factory()->create()->id,
            'commission_rate' => 0.05,
            'total_earned'    => 500.0,
        ]);

        $result = $this->service->getTopReferrers(5);

        $this->assertTrue($result->first()->relationLoaded('referrer'));
        $this->assertSame('Top Earner', $result->first()->referrer->name);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. Cache behaviour
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_overview_falls_back_to_db_when_redis_unavailable(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Redis down'));

        $overview = $this->service->getOverview();

        $this->assertInstanceOf(MetricsOverviewDTO::class, $overview);
        $this->assertSame(0, $overview->activeUsers);
    }

    /** @test */
    public function test_financial_summary_falls_back_to_db_when_redis_unavailable(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Redis down'));

        $summary = $this->service->getFinancialSummary();

        $this->assertInstanceOf(FinancialSummaryDTO::class, $summary);
    }

    /** @test */
    public function test_daily_deposit_volume_falls_back_to_db_when_redis_unavailable(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Redis down'));

        $result = $this->service->getDailyDepositVolume(30);

        $this->assertIsArray($result);
    }

    /** @test */
    public function test_pending_withdrawals_falls_back_to_db_when_redis_unavailable(): void
    {
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('Redis down'));

        $result = $this->service->getPendingWithdrawals(5);

        $this->assertCount(0, $result);
    }

    /** @test */
    public function test_cache_key_isolation_different_days_parameters(): void
    {
        $user = User::factory()->create();

        // Deposit 31 days ago — should appear in 90-day window, not 30-day
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'status'     => 'confirmed',
            'amount'     => 999.0,
            'fee_amount' => 0.0,
            'net_amount' => 999.0,
            'currency'   => 'USDT',
            'created_at' => now()->subDays(31),
        ]);

        $result30  = $this->service->getDailyDepositVolume(30);
        $result90  = $this->service->getDailyDepositVolume(90);

        $this->assertEmpty($result30);
        $this->assertNotEmpty($result90);
    }

    /** @test */
    public function test_results_use_cache_on_second_call(): void
    {
        Cache::spy();

        $this->service->getOverview();
        $this->service->getOverview();

        Cache::shouldHaveReceived('remember')->atLeast()->once();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. FinancialSummaryWidget visibility
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_financial_summary_widget_visible_to_super_admin(): void
    {
        $this->actingAs($this->admin, 'web');

        $this->assertTrue(FinancialSummaryWidget::canView());
    }

    /** @test */
    public function test_financial_summary_widget_hidden_from_manager(): void
    {
        $manager = Admin::create([
            'name'     => 'Manager',
            'email'    => 'manager@nexu.com',
            'password' => bcrypt('password'),
            'role'     => 'manager',
        ]);

        $this->actingAs($manager, 'web');

        $this->assertFalse(FinancialSummaryWidget::canView());
    }

    /** @test */
    public function test_financial_summary_widget_hidden_when_unauthenticated(): void
    {
        // No actingAs — auth()->user() returns null
        $this->assertFalse(FinancialSummaryWidget::canView());
    }
}
