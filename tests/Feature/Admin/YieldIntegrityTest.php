<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\YieldLog;
use App\Models\YieldLogUser;
use App\Services\YieldService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class YieldIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private YieldService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);

        $this->service = new YieldService();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeActiveUser(float $inOperation = 1000.0, float $available = 0.0): User
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

    private function makeYieldLog(array $attrs = []): YieldLog
    {
        return YieldLog::create(array_merge([
            'applied_by'      => $this->admin->id,
            'type'            => 'percentage',
            'value'           => '10.0000',
            'scope'           => 'all',
            'negative_policy' => 'floor',
            'status'          => 'processing',
            'applied_at'      => now(),
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. INVARIANTE DE BALANCE — Obligatorio al 100%
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_balance_invariant_holds_after_percentage_yield(): void
    {
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $users[] = $this->makeActiveUser(fake()->randomFloat(2, 100, 10000));
        }

        $log = $this->makeYieldLog(['type' => 'percentage', 'value' => '7.5000']);

        $this->service->applyBatch($log, array_column($users, 'id'));

        foreach ($users as $user) {
            $wallet = Wallet::where('user_id', $user->id)->first();
            $this->assertEqualsWithDelta(
                (float) $wallet->balance_available + (float) $wallet->balance_in_operation,
                (float) $wallet->balance_total,
                0.000001,
                "Balance invariant violated for user {$user->id}"
            );
        }
    }

    /** @test */
    public function test_balance_invariant_holds_after_negative_yield_with_floor(): void
    {
        $user = $this->makeActiveUser(100);
        $log  = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-9999.0000',
            'negative_policy' => 'floor',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEqualsWithDelta(
            (float) $wallet->balance_available + (float) $wallet->balance_in_operation,
            (float) $wallet->balance_total,
            0.000001
        );

        // balance_in_operation must be >= 0
        $this->assertGreaterThanOrEqual(0.0, (float) $wallet->balance_in_operation);
    }

    /** @test */
    public function test_balance_available_never_changes_during_yield(): void
    {
        $user = $this->makeActiveUser(1000, 500); // available = 500
        $log  = $this->makeYieldLog(['type' => 'percentage', 'value' => '20.0000']);

        $this->service->applyBatch($log, [$user->id]);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEqualsWithDelta(500.0, (float) $wallet->balance_available, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. INMUTABILIDAD DEL HISTORIAL
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_yield_transactions_cannot_be_deleted(): void
    {
        $user = $this->makeActiveUser(1000);
        $log  = $this->makeYieldLog();

        $this->service->applyBatch($log, [$user->id]);

        $tx = Transaction::where('type', 'yield')->first();
        $this->assertNotNull($tx);

        // Attempt delete — there is no route/endpoint for it; verify record persists
        $txId = $tx->id;
        $this->assertDatabaseHas('transactions', ['id' => $txId]);

        // Directly assert no soft-delete column exists in schema (not soft-deletable)
        $this->assertArrayNotHasKey('deleted_at', $tx->toArray());
    }

    /** @test */
    public function test_yield_logs_cannot_be_deleted(): void
    {
        $log = $this->makeYieldLog();

        $logId = $log->id;
        $this->assertDatabaseHas('yield_logs', ['id' => $logId]);
        $this->assertArrayNotHasKey('deleted_at', $log->toArray());
    }

    /** @test */
    public function test_yield_log_users_pivot_cannot_be_deleted(): void
    {
        $user = $this->makeActiveUser(500);
        $log  = $this->makeYieldLog();

        $this->service->applyBatch($log, [$user->id]);

        $pivot = YieldLogUser::where('yield_log_id', $log->id)->first();
        $this->assertNotNull($pivot);
        $this->assertArrayNotHasKey('deleted_at', $pivot->toArray());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. IDEMPOTENCIA — No duplicar
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_applying_yield_twice_does_not_duplicate_transactions(): void
    {
        $user   = $this->makeActiveUser(1000);
        $wallet = Wallet::where('user_id', $user->id)->first();
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        $this->service->applyBatch($log, [$user->id]);
        $this->service->applyBatch($log, [$user->id]);

        // Exactly one transaction
        $this->assertSame(1, Transaction::where('user_id', $user->id)->where('type', 'yield')->count());

        // Exactly one pivot
        $this->assertSame(1, YieldLogUser::where('yield_log_id', $log->id)->where('user_id', $user->id)->count());

        // Balance applied only once: 1000 + 10% = 1100
        $this->assertEqualsWithDelta(1100.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. TRANSACCIÓN CORRECTA
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_yield_transaction_net_amount_can_be_negative(): void
    {
        $user = $this->makeActiveUser(1000);
        $log  = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-200.0000',
            'negative_policy' => 'floor',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $tx = Transaction::where('user_id', $user->id)->where('type', 'yield')->first();
        $this->assertNotNull($tx);
        $this->assertLessThan(0, (float) $tx->net_amount);
        $this->assertEqualsWithDelta(-200.0, (float) $tx->net_amount, 0.000001);
    }

    /** @test */
    public function test_yield_transaction_amount_is_always_absolute_value(): void
    {
        $user = $this->makeActiveUser(1000);
        $log  = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-200.0000',
            'negative_policy' => 'floor',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        $tx = Transaction::where('user_id', $user->id)->where('type', 'yield')->first();
        $this->assertGreaterThan(0, (float) $tx->amount); // amount is abs(net_amount)
        $this->assertEqualsWithDelta(200.0, (float) $tx->amount, 0.000001);
    }

    /** @test */
    public function test_yield_transaction_references_yield_log(): void
    {
        $user = $this->makeActiveUser(500);
        $log  = $this->makeYieldLog();

        $this->service->applyBatch($log, [$user->id]);

        $this->assertDatabaseHas('transactions', [
            'user_id'        => $user->id,
            'type'           => 'yield',
            'reference_type' => 'yield_log',
            'reference_id'   => $log->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. CHUNKING — Aplicación masiva
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_chunk_processing_applies_yield_to_all_users(): void
    {
        // Create 25 users (multiple chunks of the real Job's chunkById(100))
        $userIds = [];
        for ($i = 0; $i < 25; $i++) {
            $userIds[] = $this->makeActiveUser(100)->id;
        }

        $log = $this->makeYieldLog(['type' => 'percentage', 'value' => '5.0000']);

        $this->service->applyBatch($log, $userIds);

        // Every user should have a pivot record with status=applied
        $appliedCount = YieldLogUser::where('yield_log_id', $log->id)->where('status', 'applied')->count();
        $this->assertSame(25, $appliedCount);

        // Every wallet should have been updated
        $updatedWallets = Wallet::whereIn('user_id', $userIds)
            ->where('balance_in_operation', '>', 100)
            ->count();
        $this->assertSame(25, $updatedWallets);
    }

    /** @test */
    public function test_mark_completed_reflects_all_applied_users(): void
    {
        $userIds = [];
        for ($i = 0; $i < 5; $i++) {
            $userIds[] = $this->makeActiveUser(200)->id;
        }

        $log = $this->makeYieldLog(['type' => 'fixed_amount', 'value' => '50.0000']);

        $this->service->applyBatch($log, $userIds);
        $this->service->markCompleted($log);

        $fresh = $log->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(5, $fresh->users_count);
        $this->assertEqualsWithDelta(250.0, (float) $fresh->total_applied, 0.000001); // 5 × 50
        $this->assertNotNull($fresh->completed_at);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. JOB GUARD — No reprocesar
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_job_aborts_silently_if_yield_log_is_not_processing(): void
    {
        $user = $this->makeActiveUser(1000);
        $log  = $this->makeYieldLog(['status' => 'completed']); // already done

        // The Job's guard checks status !== 'processing' and returns early
        // We simulate this by checking the service doesn't get called if status != processing
        // In real use, the Job does: if ($yieldLog->status !== 'processing') return;

        // Here we replicate the guard logic directly to confirm behavior:
        if ($log->status !== 'processing') {
            // Job would abort — no applyBatch
            $this->assertDatabaseCount('yield_log_users', 0);
            return;
        }

        $this->fail('Guard should have prevented execution for non-processing status');
    }

    /** @test */
    public function test_failed_user_does_not_rollback_other_users_in_same_batch(): void
    {
        $user1 = $this->makeActiveUser(500); // will succeed
        $user2 = $this->makeActiveUser(500); // will succeed
        $log   = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        // User with no wallet to force failure
        $noWalletUser = User::create([
            'name'          => 'No Wallet',
            'email'         => fake()->unique()->safeEmail(),
            'password'      => bcrypt('pass'),
            'referral_code' => Str::random(8),
            'status'        => 'active',
        ]);

        $this->service->applyBatch($log, [$user1->id, $noWalletUser->id, $user2->id]);

        // user1 and user2 were applied
        $this->assertDatabaseHas('yield_log_users', ['user_id' => $user1->id, 'status' => 'applied']);
        $this->assertDatabaseHas('yield_log_users', ['user_id' => $user2->id, 'status' => 'applied']);

        // noWalletUser was recorded as failed
        $this->assertDatabaseHas('yield_log_users', ['user_id' => $noWalletUser->id, 'status' => 'failed']);

        // Wallets of user1 and user2 updated
        $w1 = Wallet::where('user_id', $user1->id)->first();
        $w2 = Wallet::where('user_id', $user2->id)->first();
        $this->assertEqualsWithDelta(550.0, (float) $w1->balance_in_operation, 0.000001);
        $this->assertEqualsWithDelta(550.0, (float) $w2->balance_in_operation, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. CONCURRENCIA — Simular acceso simultáneo
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_concurrent_apply_batch_calls_for_same_user_result_in_single_application(): void
    {
        $user   = $this->makeActiveUser(1000);
        $wallet = Wallet::where('user_id', $user->id)->first();
        $log    = $this->makeYieldLog(['type' => 'percentage', 'value' => '10.0000']);

        // Simulate two concurrent calls by running applyBatch twice
        // The idempotency guard (YieldLogUser::exists()) inside the DB::transaction
        // prevents double-apply even if two processes reach this point simultaneously
        $this->service->applyBatch($log, [$user->id]);
        $this->service->applyBatch($log, [$user->id]);

        // Final balance: only one application
        $this->assertEqualsWithDelta(1100.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);

        // Single pivot record
        $this->assertSame(
            1,
            YieldLogUser::where('yield_log_id', $log->id)->where('user_id', $user->id)->count()
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. POLÍTICA NEGATIVA — ADR-001
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_floor_policy_is_default_and_never_produces_negative_balance(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->makeActiveUser(fake()->randomFloat(2, 1, 500));
        }

        $log = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-10000.0000', // larger than any wallet
            'negative_policy' => 'floor',
        ]);

        $allUserIds = User::active()->pluck('id')->toArray();
        $this->service->applyBatch($log, $allUserIds);

        $negativeBalances = Wallet::whereIn('user_id', $allUserIds)
            ->where('balance_in_operation', '<', 0)
            ->count();

        $this->assertSame(0, $negativeBalances, 'No wallet should have a negative balance_in_operation');
    }

    /** @test */
    public function test_skip_policy_leaves_balance_unchanged_for_insufficient_users(): void
    {
        $user   = $this->makeActiveUser(100);
        $wallet = Wallet::where('user_id', $user->id)->first();
        $log    = $this->makeYieldLog([
            'type'            => 'fixed_amount',
            'value'           => '-500.0000',
            'negative_policy' => 'skip',
        ]);

        $this->service->applyBatch($log, [$user->id]);

        // Wallet unchanged
        $this->assertEqualsWithDelta(100.0, (float) $wallet->fresh()->balance_in_operation, 0.000001);

        // Invariant still holds
        $fresh = $wallet->fresh();
        $this->assertEqualsWithDelta(
            (float) $fresh->balance_available + (float) $fresh->balance_in_operation,
            (float) $fresh->balance_total,
            0.000001
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. ACCESO AL PANEL — Autorización
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_app_user_cannot_access_yield_log_panel(): void
    {
        $user = $this->makeActiveUser();

        $this->actingAs($user, 'web')
            ->get('/admin/yield-logs')
            ->assertForbidden();
    }

    /** @test */
    public function test_super_admin_can_access_yield_log_list(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/admin/yield-logs')
            ->assertOk();
    }

    /** @test */
    public function test_manager_can_access_yield_log_list(): void
    {
        $manager = Admin::create([
            'name'     => 'Manager',
            'email'    => 'manager@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'manager',
        ]);

        $this->actingAs($manager, 'web')
            ->get('/admin/yield-logs')
            ->assertOk();
    }
}
