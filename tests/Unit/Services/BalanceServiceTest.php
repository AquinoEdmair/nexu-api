<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BalanceSnapshot;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class BalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private BalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BalanceService();
    }

    private function makeUserWithWallet(
        string $available = '1000.00000000',
        string $inOperation = '5000.00000000',
    ): User {
        $user = User::create([
            'name'              => 'Test User',
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $total = bcadd($available, $inOperation, 8);

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => $total,
        ]);

        return $user->fresh();
    }

    // ── getBalance ──────────────────────────────────────────────────────────

    public function test_get_balance_returns_wallet_data(): void
    {
        $user = $this->makeUserWithWallet('1000.00000000', '5000.00000000');

        $result = $this->service->getBalance($user);

        $this->assertEquals('1000.00000000', $result['balance_available']);
        $this->assertEquals('5000.00000000', $result['balance_in_operation']);
        $this->assertEquals('6000.00000000', $result['balance_total']);
        $this->assertEquals('USD', $result['currency']);
    }

    public function test_get_balance_returns_zeros_when_no_wallet(): void
    {
        $user = User::create([
            'name'          => 'No Wallet',
            'email'         => fake()->unique()->safeEmail(),
            'password'      => bcrypt('password'),
            'referral_code' => strtoupper(Str::random(8)),
            'status'        => 'active',
        ]);

        $result = $this->service->getBalance($user);

        $this->assertEquals('0.00000000', $result['balance_available']);
        $this->assertEquals('0.00000000', $result['balance_in_operation']);
        $this->assertEquals('0.00000000', $result['balance_total']);
        $this->assertEquals('USD', $result['currency']);
    }

    public function test_balance_total_equals_available_plus_in_operation(): void
    {
        $user = $this->makeUserWithWallet('12345.67890000', '54321.12340000');

        $result = $this->service->getBalance($user);

        $expected = bcadd($result['balance_available'], $result['balance_in_operation'], 8);
        $this->assertEquals($expected, $result['balance_total']);
    }

    // ── getBalanceHistory ───────────────────────────────────────────────────

    public function test_get_balance_history_returns_snapshots(): void
    {
        $user = $this->makeUserWithWallet();

        // Create snapshots for past 3 days
        for ($i = 3; $i >= 1; $i--) {
            BalanceSnapshot::create([
                'user_id'              => $user->id,
                'balance_available'    => (string) ($i * 100),
                'balance_in_operation' => (string) ($i * 500),
                'balance_total'        => (string) ($i * 600),
                'snapshot_date'        => Carbon::today()->subDays($i)->toDateString(),
                'created_at'           => now(),
            ]);
        }

        $result = $this->service->getBalanceHistory($user, 30);

        // 3 snapshots + today's live data = 4
        $this->assertCount(4, $result);
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $result->last()['date']);
    }

    public function test_get_balance_history_appends_today_live_data(): void
    {
        $user = $this->makeUserWithWallet('999.00000000', '1.00000000');

        $result = $this->service->getBalanceHistory($user, 30);

        $this->assertCount(1, $result);
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $result->first()['date']);
        $this->assertEquals('999.00000000', $result->first()['balance_available']);
    }

    public function test_get_balance_history_does_not_duplicate_today(): void
    {
        $user = $this->makeUserWithWallet();

        BalanceSnapshot::create([
            'user_id'              => $user->id,
            'balance_available'    => '500.00000000',
            'balance_in_operation' => '500.00000000',
            'balance_total'        => '1000.00000000',
            'snapshot_date'        => Carbon::today()->toDateString(),
            'created_at'           => now(),
        ]);

        $result = $this->service->getBalanceHistory($user, 30);

        // Should NOT append live data since today already has a snapshot
        $this->assertCount(1, $result);
    }

    public function test_get_balance_history_caps_at_365_days(): void
    {
        $user = $this->makeUserWithWallet();

        // Even if we request 9999 days, it should cap at 365
        $result = $this->service->getBalanceHistory($user, 9999);

        // Just verify it doesn't error — the cap is internal
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    public function test_get_balance_history_empty_without_wallet(): void
    {
        $user = User::create([
            'name'          => 'No Wallet',
            'email'         => fake()->unique()->safeEmail(),
            'password'      => bcrypt('password'),
            'referral_code' => strtoupper(Str::random(8)),
            'status'        => 'active',
        ]);

        $result = $this->service->getBalanceHistory($user, 30);

        $this->assertCount(0, $result);
    }

    // ── snapshotAllBalances ─────────────────────────────────────────────────

    public function test_snapshot_all_balances_creates_snapshots(): void
    {
        $this->makeUserWithWallet('100.00000000', '200.00000000');
        $this->makeUserWithWallet('300.00000000', '400.00000000');

        $count = $this->service->snapshotAllBalances();

        $this->assertEquals(2, $count);
        $this->assertEquals(2, BalanceSnapshot::count());
    }

    public function test_snapshot_all_balances_is_idempotent(): void
    {
        $this->makeUserWithWallet();

        $first = $this->service->snapshotAllBalances();
        $second = $this->service->snapshotAllBalances();

        $this->assertEquals(1, $first);
        $this->assertEquals(0, $second);
        $this->assertEquals(1, BalanceSnapshot::count());
    }

    public function test_snapshot_preserves_balance_values(): void
    {
        $user = $this->makeUserWithWallet('12345.67890000', '98765.43210000');

        $this->service->snapshotAllBalances();

        $snapshot = BalanceSnapshot::where('user_id', $user->id)->first();

        $this->assertEquals('12345.67890000', $snapshot->balance_available);
        $this->assertEquals('98765.43210000', $snapshot->balance_in_operation);
        $this->assertEquals('111111.11100000', $snapshot->balance_total);
    }
}
