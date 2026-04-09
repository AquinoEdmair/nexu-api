<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\ApplyYieldDTO;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\YieldLog;
use App\Models\YieldLogUser;
use App\Services\YieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class YieldServiceTest extends TestCase
{
    use RefreshDatabase;

    private YieldService $service;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new YieldService();

        $this->admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(string $status = 'active'): User
    {
        return User::create([
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => $status,
            'email_verified_at' => now(),
        ]);
    }

    private function makeWallet(User $user, float $available = 0.0, float $inOperation = 1000.0): Wallet
    {
        $total = $available + $inOperation;

        return Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => $total,
        ]);
    }

    private function makeYieldLog(array $attrs = []): YieldLog
    {
        return YieldLog::create(array_merge([
            'applied_by'      => $this->admin->id,
            'type'            => 'percentage',
            'value'           => '5.00',
            'scope'           => 'all',
            'negative_policy' => 'floor',
            'status'          => 'processing',
            'applied_at'      => now(),
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. HAPPY PATH — Porcentaje
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_apply_batch_applies_percentage_yield_correctly(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 0, 1000);
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '5.0000']);

        $this->service->applyBatch($log, [$user->id]);

        // 5% de 1000 = 50
        $this->assertEqualsWithDelta(1050.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);
    }

    /** @test */
    public function test_apply_batch_applies_fixed_amount_correctly(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 0, 1000);
        $log    = $this->makeYieldLog(['type' => 'fixed_amount', 'value' => '75.0000']);

        $this->service->applyBatch($log, [$user->id]);

        $this->assertEqualsWithDelta(1075.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. INTEGRIDAD — Registros creados
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_apply_batch_creates_yield_transaction(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 0, 500);
        $log = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        $this->service->applyBatch($log, [$user->id]);

        $this->assertDatabaseHas('transactions', [
            'user_id'        => $user->id,
            'type'           => 'yield',
            'status'         => 'confirmed',
            'currency'       => 'USD',
            'reference_type' => 'yield_log',
            'reference_id'   => $log->id,
        ]);
    }

    /** @test */
    public function test_apply_batch_creates_yield_log_user_pivot(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 0, 500);
        $log = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        $this->service->applyBatch($log, [$user->id]);

        $this->assertDatabaseHas('yield_log_users', [
            'yield_log_id' => $log->id,
            'user_id'      => $user->id,
            'status'       => 'applied',
        ]);
    }

    /** @test */
    public function test_apply_batch_records_correct_balance_before_and_after(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 200, 800);
        $log = $this->makeYieldLog(['type' => 'fixed_amount', 'value' => '100.0000']);

        $this->service->applyBatch($log, [$user->id]);

        $pivot = YieldLogUser::where('yield_log_id', $log->id)->where('user_id', $user->id)->first();

        $this->assertNotNull($pivot);
        $this->assertEqualsWithDelta(800.0, (float) $pivot->balance_before, 0.000001);
        $this->assertEqualsWithDelta(900.0, (float) $pivot->balance_after, 0.000001);
        $this->assertEqualsWithDelta(100.0, (float) $pivot->amount_applied, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. INVARIANTE DE BALANCE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_balance_total_invariant_holds_after_yield(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 300, 700);
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        $this->service->applyBatch($log, [$user->id]);

        $fresh = $wallet->fresh();
        $this->assertEqualsWithDelta(
            (float) $fresh->balance_available + (float) $fresh->balance_in_operation,
            (float) $fresh->balance_total,
            0.000001,
            'balance_total must equal balance_available + balance_in_operation'
        );
    }

    /** @test */
    public function test_positive_yield_does_not_touch_balance_available(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 500, 1000);
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '5.0000']);

        $this->service->applyBatch($log, [$user->id]);

        // balance_available must remain unchanged
        $this->assertEqualsWithDelta(500.0, (float) $wallet->fresh()->balance_available, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. YIELD NEGATIVO
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_negative_percentage_yield_reduces_balance(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 0, 1000);
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '-10.0000']);

        $this->service->applyBatch($log, [$user->id]);

        // -10% de 1000 = -100 → 900
        $this->assertEqualsWithDelta(900.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);
    }

    /** @test */
    public function test_floor_policy_does_not_allow_negative_balance(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 0, 100);
        $log    = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-500.0000',
            'negative_policy' => 'floor',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $fresh = $wallet->fresh();

        // Floor: never goes below 0
        $this->assertEqualsWithDelta(0.0, (float) $fresh->balance_in_operation, 0.000001);
        $this->assertGreaterThanOrEqual(0.0, (float) $fresh->balance_in_operation);
    }

    /** @test */
    public function test_floor_policy_applies_maximum_possible_deduction(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 0, 100);
        $log = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-500.0000',
            'negative_policy' => 'floor',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $pivot = YieldLogUser::where('yield_log_id', $log->id)->where('user_id', $user->id)->first();

        // amount_applied should be -100 (the full balance), not -500
        $this->assertEqualsWithDelta(-100.0, (float) $pivot->amount_applied, 0.000001);
        $this->assertSame('applied', $pivot->status);
    }

    /** @test */
    public function test_skip_policy_omits_user_with_insufficient_balance(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 0, 100);
        $log    = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-500.0000',
            'negative_policy' => 'skip',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $fresh = $wallet->fresh();
        $pivot = YieldLogUser::where('yield_log_id', $log->id)->where('user_id', $user->id)->first();

        // Balance unchanged
        $this->assertEqualsWithDelta(100.0, (float) $fresh->balance_in_operation, 0.000001);
        // Pivot status = skipped
        $this->assertSame('skipped', $pivot->status);
        $this->assertEqualsWithDelta(0.0, (float) $pivot->amount_applied, 0.000001);
    }

    /** @test */
    public function test_skip_policy_does_not_create_transaction(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 0, 100);
        $log = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-500.0000',
            'negative_policy' => 'skip',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $this->assertDatabaseMissing('transactions', [
            'user_id'        => $user->id,
            'type'           => 'yield',
            'reference_type' => 'yield_log',
            'reference_id'   => $log->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. IDEMPOTENCIA — No duplicar
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_apply_batch_is_idempotent_does_not_double_apply(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 0, 1000);
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        // Apply twice
        $this->service->applyBatch($log, [$user->id]);
        $this->service->applyBatch($log, [$user->id]);

        // Only applied once: 1000 + 100 = 1100, NOT 1000 + 100 + 110 = 1210
        $this->assertEqualsWithDelta(1100.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);

        // Only one YieldLogUser record
        $this->assertSame(1, YieldLogUser::where('yield_log_id', $log->id)->where('user_id', $user->id)->count());

        // Only one transaction
        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('type', 'yield')->count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. FALLOS PARCIALES — Aislamiento por usuario
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_failed_user_does_not_rollback_committed_users(): void
    {
        $goodUser = $this->makeUser();
        $this->makeWallet($goodUser, 0, 500);

        // badUser has NO wallet — will trigger ModelNotFoundException inside applyToUser
        $badUser = $this->makeUser();

        $log = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        // Process good + bad user in same batch (bad user has no wallet)
        $this->service->applyBatch($log, [$goodUser->id, $badUser->id]);

        // Good user was applied successfully
        $this->assertDatabaseHas('yield_log_users', [
            'user_id' => $goodUser->id,
            'status'  => 'applied',
        ]);

        // Bad user is recorded as failed
        $this->assertDatabaseHas('yield_log_users', [
            'user_id' => $badUser->id,
            'status'  => 'failed',
        ]);

        // Good user's balance was updated
        $goodWallet = Wallet::where('user_id', $goodUser->id)->first();
        $this->assertEqualsWithDelta(550.0, (float) $goodWallet->balance_in_operation, 0.000001);
    }

    /** @test */
    public function test_apply_batch_raises_exception_when_failure_rate_exceeds_10_percent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Batch failure rate too high/');

        // Create 9 users with no wallets (100% failure rate, well above 10%)
        $userIds = [];
        for ($i = 0; $i < 9; $i++) {
            $userIds[] = $this->makeUser()->id;
        }

        $log = $this->makeYieldLog(['type' => 'percentage', 'value' => '5.0000']);

        $this->service->applyBatch($log, $userIds);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. markCompleted + markFailed
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_mark_completed_sets_correct_totals_from_applied_pivots(): void
    {
        $log = $this->makeYieldLog();

        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        YieldLogUser::create([
            'yield_log_id'   => $log->id,
            'user_id'        => $user1->id,
            'balance_before' => 1000,
            'balance_after'  => 1050,
            'amount_applied' => 50,
            'status'         => 'applied',
        ]);

        YieldLogUser::create([
            'yield_log_id'   => $log->id,
            'user_id'        => $user2->id,
            'balance_before' => 2000,
            'balance_after'  => 2100,
            'amount_applied' => 100,
            'status'         => 'applied',
        ]);

        $this->service->markCompleted($log);

        $fresh = $log->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(2, $fresh->users_count);
        $this->assertEqualsWithDelta(150.0, (float) $fresh->total_applied, 0.000001);
        $this->assertNotNull($fresh->completed_at);
    }

    /** @test */
    public function test_mark_completed_excludes_skipped_and_failed_from_totals(): void
    {
        $log  = $this->makeYieldLog();
        $user = $this->makeUser();

        YieldLogUser::create([
            'yield_log_id'   => $log->id,
            'user_id'        => $user->id,
            'balance_before' => 1000,
            'balance_after'  => 1050,
            'amount_applied' => 50,
            'status'         => 'applied',
        ]);

        $user2 = $this->makeUser();
        YieldLogUser::create([
            'yield_log_id'   => $log->id,
            'user_id'        => $user2->id,
            'balance_before' => 100,
            'balance_after'  => 100,
            'amount_applied' => 0,
            'status'         => 'skipped',
        ]);

        $this->service->markCompleted($log);

        $fresh = $log->fresh();
        // Only 1 user_count (applied, not skipped)
        $this->assertSame(1, $fresh->users_count);
        $this->assertEqualsWithDelta(50.0, (float) $fresh->total_applied, 0.000001);
    }

    /** @test */
    public function test_mark_failed_sets_error_message_and_status(): void
    {
        $log = $this->makeYieldLog();

        $this->service->markFailed($log, 'Something went wrong');

        $fresh = $log->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertStringContainsString('Something went wrong', $fresh->error_message);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. createAndDispatch
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_create_and_dispatch_creates_yield_log_with_processing_status(): void
    {
        $dto = new ApplyYieldDTO(
            type:           'percentage',
            value:          5.0,
            scope:          'all',
            userId:         null,
            description:    'Test yield',
            negativePolicy: 'floor',
        );

        $log = $this->service->createAndDispatch($dto, $this->admin);

        $this->assertInstanceOf(YieldLog::class, $log);
        $this->assertSame('processing', $log->status);
        $this->assertSame('percentage', $log->type);
        $this->assertSame($this->admin->id, $log->applied_by);
        $this->assertNotNull($log->applied_at);
    }
}
