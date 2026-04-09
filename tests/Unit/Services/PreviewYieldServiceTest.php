<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\ApplyYieldDTO;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\YieldLog;
use App\Models\YieldLogUser;
use App\Services\PreviewYieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PreviewYieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private PreviewYieldService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PreviewYieldService();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeActiveUserWithWallet(float $inOperation = 1000.0, float $available = 0.0): User
    {
        $user = User::create([
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => $available + $inOperation,
        ]);

        return $user;
    }

    private function dto(array $attrs = []): ApplyYieldDTO
    {
        return new ApplyYieldDTO(
            type:           $attrs['type'] ?? 'percentage',
            value:          $attrs['value'] ?? 10.0,
            scope:          $attrs['scope'] ?? 'all',
            userId:         $attrs['userId'] ?? null,
            description:    $attrs['description'] ?? null,
            negativePolicy: $attrs['negativePolicy'] ?? 'floor',
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Cálculos correctos
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_returns_correct_totals_for_percentage(): void
    {
        $this->makeActiveUserWithWallet(1000);
        $this->makeActiveUserWithWallet(2000);

        $preview = $this->service->calculate($this->dto(['type' => 'percentage', 'value' => 10.0]));

        // 10% de 1000 + 10% de 2000 = 100 + 200 = 300
        $this->assertSame(2, $preview->totalUsers);
        $this->assertEqualsWithDelta(300.0, (float) $preview->totalAmountToApply, 0.000001);
        $this->assertEqualsWithDelta(3000.0, (float) $preview->systemBalanceBefore, 0.000001);
        $this->assertEqualsWithDelta(3300.0, (float) $preview->systemBalanceAfter, 0.000001);
    }

    /** @test */
    public function test_calculate_returns_correct_totals_for_fixed_amount(): void
    {
        $this->makeActiveUserWithWallet(500);
        $this->makeActiveUserWithWallet(800);

        $preview = $this->service->calculate($this->dto(['type' => 'fixed_amount', 'value' => 50.0]));

        // 50 + 50 = 100
        $this->assertSame(2, $preview->totalUsers);
        $this->assertEqualsWithDelta(100.0, (float) $preview->totalAmountToApply, 0.000001);
    }

    /** @test */
    public function test_calculate_returns_correct_row_data_for_each_user(): void
    {
        $this->makeActiveUserWithWallet(1000);

        $preview = $this->service->calculate($this->dto(['type' => 'percentage', 'value' => 5.0]));

        $this->assertCount(1, $preview->userRows);
        $row = $preview->userRows->first();

        $this->assertEqualsWithDelta(1000.0, (float) $row->balanceBefore, 0.000001);
        $this->assertEqualsWithDelta(50.0, (float) $row->amountToApply, 0.000001);
        $this->assertEqualsWithDelta(1050.0, (float) $row->balanceAfter, 0.000001);
        $this->assertFalse($row->wouldGoNegative);
        $this->assertFalse($row->wouldBeSkipped);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Detección de negativos
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_detects_users_going_negative(): void
    {
        $this->makeActiveUserWithWallet(100); // will go negative with -500 fixed

        $preview = $this->service->calculate($this->dto(['type' => 'fixed_amount', 'value' => -500.0]));

        $this->assertTrue($preview->hasUsersGoingNegative);
        $this->assertTrue($preview->userRows->first()->wouldGoNegative);
    }

    /** @test */
    public function test_calculate_with_skip_policy_counts_skipped_users(): void
    {
        $this->makeActiveUserWithWallet(100);  // will be skipped
        $this->makeActiveUserWithWallet(1000); // will be applied

        $preview = $this->service->calculate($this->dto([
            'type'            => 'fixed_amount',
            'value'           => -500.0,
            'negativePolicy'  => 'skip',
        ]));

        $this->assertSame(1, $preview->usersSkippedByPolicy);
        $this->assertTrue($preview->hasUsersGoingNegative);

        $skippedRow = $preview->userRows->firstWhere('wouldBeSkipped', true);
        $this->assertNotNull($skippedRow);
        $this->assertEqualsWithDelta(0.0, (float) $skippedRow->amountToApply, 0.000001);
    }

    /** @test */
    public function test_calculate_with_floor_policy_limits_balance_after_to_zero(): void
    {
        $this->makeActiveUserWithWallet(100); // balance_in_operation = 100

        $preview = $this->service->calculate($this->dto([
            'type'           => 'fixed_amount',
            'value'          => -500.0,
            'negativePolicy' => 'floor',
        ]));

        $row = $preview->userRows->first();

        // Floor: balance_after capped at 0, amount = -100
        $this->assertEqualsWithDelta(0.0, (float) $row->balanceAfter, 0.000001);
        $this->assertEqualsWithDelta(-100.0, (float) $row->amountToApply, 0.000001);
        $this->assertFalse($row->wouldBeSkipped);
        $this->assertTrue($row->wouldGoNegative);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. Filtros de scope
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_excludes_blocked_users(): void
    {
        $this->makeActiveUserWithWallet(1000);

        // Blocked user — should not appear in preview
        $blocked = User::create([
            'name'          => 'Blocked',
            'email'         => fake()->unique()->safeEmail(),
            'password'      => bcrypt('password'),
            'referral_code' => strtoupper(Str::random(8)),
            'status'        => 'blocked',
        ]);
        Wallet::create([
            'user_id'              => $blocked->id,
            'balance_available'    => 0,
            'balance_in_operation' => 5000,
            'balance_total'        => 5000,
        ]);

        $preview = $this->service->calculate($this->dto());

        // Only 1 active user
        $this->assertSame(1, $preview->totalUsers);
    }

    /** @test */
    public function test_calculate_with_specific_user_scope_returns_only_that_user(): void
    {
        $user1 = $this->makeActiveUserWithWallet(500);
        $this->makeActiveUserWithWallet(1000); // should not appear

        $preview = $this->service->calculate($this->dto([
            'scope'  => 'specific_user',
            'userId' => $user1->id,
            'value'  => 10.0,
        ]));

        $this->assertSame(1, $preview->totalUsers);
        $this->assertSame($user1->id, $preview->userRows->first()->userId);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Sin efectos secundarios
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_does_not_write_to_database(): void
    {
        $user   = $this->makeActiveUserWithWallet(1000);
        $wallet = Wallet::where('user_id', $user->id)->first();

        $balanceBefore = (float) $wallet->balance_in_operation;

        $this->service->calculate($this->dto(['type' => 'percentage', 'value' => 20.0]));

        // Wallet unchanged
        $this->assertEqualsWithDelta($balanceBefore, (float) $wallet->fresh()->balance_in_operation, 0.000001);

        // No transactions created
        $this->assertDatabaseCount('transactions', 0);

        // No yield_log_users created
        $this->assertDatabaseCount('yield_log_users', 0);

        // No yield_logs created
        $this->assertDatabaseCount('yield_logs', 0);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Límite de filas
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_limits_preview_rows_to_100(): void
    {
        // Create 105 active users
        for ($i = 0; $i < 105; $i++) {
            $this->makeActiveUserWithWallet(100);
        }

        $preview = $this->service->calculate($this->dto());

        $this->assertSame(105, $preview->totalUsers);
        $this->assertCount(100, $preview->userRows);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. Edge cases
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_calculate_with_zero_users_returns_empty_preview(): void
    {
        $preview = $this->service->calculate($this->dto());

        $this->assertSame(0, $preview->totalUsers);
        $this->assertEqualsWithDelta(0.0, (float) $preview->totalAmountToApply, 0.000001);
        $this->assertCount(0, $preview->userRows);
        $this->assertFalse($preview->hasUsersGoingNegative);
    }

    /** @test */
    public function test_calculate_with_zero_yield_value_returns_zero_amounts(): void
    {
        $this->makeActiveUserWithWallet(1000);

        $preview = $this->service->calculate($this->dto(['value' => 0.0]));

        $this->assertEqualsWithDelta(0.0, (float) $preview->totalAmountToApply, 0.000001);
        $this->assertEqualsWithDelta(0.0, (float) $preview->userRows->first()->amountToApply, 0.000001);
    }

    /** @test */
    public function test_system_balance_after_equals_before_plus_total_amount(): void
    {
        $this->makeActiveUserWithWallet(500);
        $this->makeActiveUserWithWallet(1500);

        $preview = $this->service->calculate($this->dto(['type' => 'percentage', 'value' => 8.0]));

        $expected = (float) $preview->systemBalanceBefore + (float) $preview->totalAmountToApply;
        $this->assertEqualsWithDelta($expected, (float) $preview->systemBalanceAfter, 0.000001);
    }
}
