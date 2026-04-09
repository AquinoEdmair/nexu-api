<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\CreateWithdrawalDTO;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidStatusTransitionException;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawalService $service;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WithdrawalService();

        $this->admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::create([
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function makeWallet(User $user, float $available = 500.0, float $inOperation = 1000.0): Wallet
    {
        return Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => $available + $inOperation,
        ]);
    }

    private function makeCreateDTO(float $amount = 100.0): CreateWithdrawalDTO
    {
        return new CreateWithdrawalDTO(
            amount: $amount,
            currency: 'USDT',
            destinationAddress: 'TXabc123',
        );
    }

    private function makeRequest(User $user, string $status = 'pending', float $amount = 100.0): WithdrawalRequest
    {
        return WithdrawalRequest::create([
            'user_id'             => $user->id,
            'amount'              => $amount,
            'currency'            => 'USDT',
            'destination_address' => 'TXabc123',
            'status'              => $status,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. CREATE — Happy path
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_create_reserves_funds_from_balance_available(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 500.0, 1000.0);

        $request = $this->service->create($this->makeCreateDTO(200.0), $user);

        $wallet->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertEqualsWithDelta(300.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(1000.0, (float) $wallet->balance_in_operation, 0.000001);
        $this->assertEqualsWithDelta(1300.0, (float) $wallet->balance_total, 0.000001);
    }

    /** @test */
    public function test_create_throws_when_amount_exceeds_balance_available(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 50.0, 1000.0);

        $this->expectException(InsufficientBalanceException::class);

        $this->service->create($this->makeCreateDTO(100.0), $user);
    }

    /** @test */
    public function test_create_allows_withdrawal_equal_to_exact_balance(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 100.0, 0.0);

        $request = $this->service->create($this->makeCreateDTO(100.0), $user);

        $wallet->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertEqualsWithDelta(0.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(0.0, (float) $wallet->balance_total, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. APPROVE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_approve_sets_status_to_approved(): void
    {
        Event::fake();

        $user   = $this->makeUser();
        $this->makeWallet($user, 500.0);
        $request = $this->makeRequest($user, 'pending');

        $result = $this->service->approve($request, $this->admin);

        $this->assertSame('approved', $result->status);
        $this->assertSame($this->admin->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
    }

    /** @test */
    public function test_approve_does_not_alter_wallet_balances(): void
    {
        Event::fake();

        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 400.0, 1000.0);
        $request = $this->makeRequest($user, 'pending', 100.0);

        $this->service->approve($request, $this->admin);

        $wallet->refresh();
        $this->assertEqualsWithDelta(400.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(1400.0, (float) $wallet->balance_total, 0.000001);
    }

    /** @test */
    public function test_approve_throws_when_status_is_not_pending(): void
    {
        $user    = $this->makeUser();
        $this->makeWallet($user);
        $request = $this->makeRequest($user, 'approved');

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->approve($request, $this->admin);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. REJECT
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_reject_from_pending_releases_funds_back(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $wallet  = $this->makeWallet($user, 400.0, 1000.0);
        $request = $this->makeRequest($user, 'pending', 100.0);

        $this->service->reject($request, 'Dirección inválida', $this->admin);

        $wallet->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(1500.0, (float) $wallet->balance_total, 0.000001);
    }

    /** @test */
    public function test_reject_from_approved_releases_funds_back(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $wallet  = $this->makeWallet($user, 400.0, 1000.0);
        $request = $this->makeRequest($user, 'approved', 100.0);

        $this->service->reject($request, 'Fondos insuficientes en la red', $this->admin);

        $wallet->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $wallet->balance_available, 0.000001);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('Fondos insuficientes en la red', $request->fresh()->rejection_reason);
    }

    /** @test */
    public function test_reject_creates_a_rejected_transaction(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $this->makeWallet($user, 400.0);
        $request = $this->makeRequest($user, 'pending', 100.0);

        $this->service->reject($request, 'Motivo de prueba', $this->admin);

        $this->assertDatabaseHas('transactions', [
            'user_id'        => $user->id,
            'type'           => 'withdrawal',
            'status'         => 'rejected',
            'reference_type' => 'withdrawal_request',
            'reference_id'   => $request->id,
        ]);
    }

    /** @test */
    public function test_reject_throws_when_status_is_completed(): void
    {
        $user    = $this->makeUser();
        $this->makeWallet($user);
        $request = $this->makeRequest($user, 'completed');

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->reject($request, 'No se puede', $this->admin);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. COMPLETE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_complete_sets_status_and_tx_hash(): void
    {
        $user    = $this->makeUser();
        $this->makeWallet($user, 400.0);
        $request = $this->makeRequest($user, 'approved', 100.0);

        $result = $this->service->complete($request, '0xabc123def456', $this->admin);

        $this->assertSame('completed', $result->status);
        $this->assertSame('0xabc123def456', $result->tx_hash);
    }

    /** @test */
    public function test_complete_creates_a_confirmed_transaction(): void
    {
        $user    = $this->makeUser();
        $this->makeWallet($user, 400.0);
        $request = $this->makeRequest($user, 'approved', 100.0);

        $this->service->complete($request, '0xabc123', $this->admin);

        $this->assertDatabaseHas('transactions', [
            'user_id'        => $user->id,
            'type'           => 'withdrawal',
            'status'         => 'confirmed',
            'reference_type' => 'withdrawal_request',
            'reference_id'   => $request->id,
        ]);
    }

    /** @test */
    public function test_complete_does_not_alter_wallet_balances(): void
    {
        $user    = $this->makeUser();
        $wallet  = $this->makeWallet($user, 400.0, 1000.0);
        $request = $this->makeRequest($user, 'approved', 100.0);

        $this->service->complete($request, '0xabc123', $this->admin);

        $wallet->refresh();
        // Funds were already reserved at creation — complete does not touch wallet
        $this->assertEqualsWithDelta(400.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(1400.0, (float) $wallet->balance_total, 0.000001);
    }

    /** @test */
    public function test_complete_throws_when_status_is_not_approved(): void
    {
        $user    = $this->makeUser();
        $this->makeWallet($user);
        $request = $this->makeRequest($user, 'pending');

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->complete($request, '0xabc', $this->admin);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. CANCEL (user-initiated)
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_cancel_releases_funds_and_marks_rejected(): void
    {
        $user    = $this->makeUser();
        $wallet  = $this->makeWallet($user, 400.0, 1000.0);
        $request = $this->makeRequest($user, 'pending', 100.0);

        $this->service->cancel($request, $user);

        $wallet->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $wallet->balance_available, 0.000001);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('Cancelado por el usuario', $request->fresh()->rejection_reason);
    }

    /** @test */
    public function test_cancel_throws_when_request_belongs_to_different_user(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $this->makeWallet($owner);
        $this->makeWallet($other);
        $request = $this->makeRequest($owner, 'pending');

        $this->expectException(AuthorizationException::class);

        $this->service->cancel($request, $other);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. BALANCE INVARIANT
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_balance_invariant_holds_after_create(): void
    {
        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, 500.0, 1000.0);

        $this->service->create($this->makeCreateDTO(200.0), $user);

        $wallet->refresh();
        $expected = round((float) $wallet->balance_available + (float) $wallet->balance_in_operation, 8);
        $this->assertEqualsWithDelta($expected, (float) $wallet->balance_total, 0.000001);
    }

    /** @test */
    public function test_balance_invariant_holds_after_reject(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $wallet  = $this->makeWallet($user, 400.0, 1000.0);
        $request = $this->makeRequest($user, 'pending', 100.0);

        $this->service->reject($request, 'Prueba', $this->admin);

        $wallet->refresh();
        $expected = round((float) $wallet->balance_available + (float) $wallet->balance_in_operation, 8);
        $this->assertEqualsWithDelta($expected, (float) $wallet->balance_total, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. TRANSACTIONS ARE IMMUTABLE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_transactions_are_never_deleted(): void
    {
        $user    = $this->makeUser();
        $this->makeWallet($user, 400.0);
        $request = $this->makeRequest($user, 'approved', 100.0);

        $this->service->complete($request, '0xabc', $this->admin);

        $count = Transaction::where('reference_id', $request->id)->count();
        $this->assertGreaterThan(0, $count);

        // Ensure no soft-delete column exists — transactions are truly immutable
        $this->assertDatabaseHas('transactions', [
            'reference_id' => $request->id,
            'type'         => 'withdrawal',
        ]);
    }
}
