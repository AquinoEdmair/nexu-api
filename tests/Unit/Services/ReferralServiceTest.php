<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\EliteTier;
use App\Models\ElitePoint;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ReferralService;
use App\Services\UserAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReferralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReferralService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(?string $email = null): User
    {
        return User::create([
            'name'              => fake()->name(),
            'email'             => $email ?? fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function makeWallet(User $user, float $available = 0.0, float $inOperation = 0.0): Wallet
    {
        return Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => $available + $inOperation,
        ]);
    }

    private function makeReferral(User $referrer, User $referred, float $commissionRate = 0.05): Referral
    {
        return Referral::create([
            'referrer_id'     => $referrer->id,
            'referred_id'     => $referred->id,
            'commission_rate' => $commissionRate,
            'total_earned'    => '0.00000000',
        ]);
    }

    private function makeDepositTransaction(User $user, float $netAmount): Transaction
    {
        return Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'amount'     => $netAmount,
            'fee_amount' => '0.00000000',
            'net_amount' => $netAmount,
            'currency'   => 'USDT',
            'status'     => 'confirmed',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Happy path
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_happy_path_applies_commission_and_elite_points(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        $this->makeWallet($referrer, 0.0);
        $this->makeReferral($referrer, $referred, 0.05);

        $deposit = $this->makeDepositTransaction($referred, 100.0);

        $commissionTx = $this->service->applyDepositCommission($deposit);

        // Commission transaction created
        $this->assertNotNull($commissionTx);
        $this->assertSame('referral_commission', $commissionTx->type);
        $this->assertEqualsWithDelta(5.0, (float) $commissionTx->net_amount, 0.000001);

        // Referrer wallet credited
        $wallet = Wallet::where('user_id', $referrer->id)->first();
        $this->assertEqualsWithDelta(5.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(5.0, (float) $wallet->balance_total, 0.000001);

        // Elite points created (1 point per $1 of net deposit = 100 points)
        $points = ElitePoint::where('user_id', $referrer->id)->first();
        $this->assertNotNull($points);
        $this->assertEqualsWithDelta(100.0, (float) $points->points, 0.01);
        $this->assertSame($commissionTx->id, $points->transaction_id);

        // total_earned on referral row updated
        $referral = Referral::where('referrer_id', $referrer->id)->first();
        $this->assertEqualsWithDelta(5.0, (float) $referral->total_earned, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. Idempotency
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_duplicate_deposit_event_creates_only_one_commission(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        $this->makeWallet($referrer, 0.0);
        $this->makeReferral($referrer, $referred, 0.05);

        $deposit = $this->makeDepositTransaction($referred, 100.0);

        // Apply twice
        $this->service->applyDepositCommission($deposit);
        $secondResult = $this->service->applyDepositCommission($deposit);

        $this->assertNull($secondResult);

        $commissionCount = Transaction::where('user_id', $referrer->id)
            ->where('type', 'referral_commission')
            ->count();
        $this->assertSame(1, $commissionCount);

        // Wallet should only have 5, not 10
        $wallet = Wallet::where('user_id', $referrer->id)->first();
        $this->assertEqualsWithDelta(5.0, (float) $wallet->balance_available, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. No referrer → null, no DB changes
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_no_referral_relationship_returns_null_and_no_db_changes(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, 0.0);

        $deposit = $this->makeDepositTransaction($user, 100.0);

        $result = $this->service->applyDepositCommission($deposit);

        $this->assertNull($result);
        $this->assertSame(0, Transaction::where('type', 'referral_commission')->count());
        $this->assertSame(0, ElitePoint::count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Rate 0 → no commission TX, but elite_points ARE created
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_zero_commission_rate_skips_commission_tx_but_creates_elite_points(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        $this->makeWallet($referrer, 0.0);
        $this->makeReferral($referrer, $referred, 0.0);

        $deposit = $this->makeDepositTransaction($referred, 100.0);

        $commissionTx = $this->service->applyDepositCommission($deposit);

        $this->assertNull($commissionTx);

        // No monetary transaction
        $this->assertSame(0, Transaction::where('type', 'referral_commission')->count());

        // Wallet untouched
        $wallet = Wallet::where('user_id', $referrer->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $wallet->balance_available, 0.000001);

        // Elite points ARE still created
        $points = ElitePoint::where('user_id', $referrer->id)->first();
        $this->assertNotNull($points);
        $this->assertEqualsWithDelta(100.0, (float) $points->points, 0.01);
        $this->assertNull($points->transaction_id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Multiple deposits → points accumulate, tier progresses
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_multiple_deposits_accumulate_elite_points(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        $this->makeWallet($referrer, 0.0);
        $this->makeReferral($referrer, $referred, 0.05);

        $deposit1 = $this->makeDepositTransaction($referred, 600.0);
        $deposit2 = $this->makeDepositTransaction($referred, 500.0);

        $this->service->applyDepositCommission($deposit1);
        $this->service->applyDepositCommission($deposit2);

        $totalPoints = (float) ElitePoint::where('user_id', $referrer->id)->sum('points');
        $this->assertEqualsWithDelta(1100.0, $totalPoints, 0.01);

        $tier = EliteTier::fromPoints($totalPoints);
        $this->assertSame(EliteTier::Silver, $tier);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. Immutability: commission transactions are never mutated
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_commission_transactions_are_immutable(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        $this->makeWallet($referrer, 0.0);
        $this->makeReferral($referrer, $referred, 0.05);

        $deposit = $this->makeDepositTransaction($referred, 100.0);

        $commissionTx = $this->service->applyDepositCommission($deposit);
        $this->assertNotNull($commissionTx);

        $originalAmount    = $commissionTx->net_amount;
        $originalCreatedAt = $commissionTx->created_at->toIso8601String();

        // Re-fetch from DB
        $commissionTx->refresh();
        $this->assertSame((string) $originalAmount, (string) $commissionTx->net_amount);
        $this->assertSame($originalCreatedAt, $commissionTx->created_at->toIso8601String());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. EliteTier transitions
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_elite_tier_transitions(): void
    {
        $this->assertSame(EliteTier::Bronze,   EliteTier::fromPoints(0));
        $this->assertSame(EliteTier::Bronze,   EliteTier::fromPoints(999));
        $this->assertSame(EliteTier::Silver,   EliteTier::fromPoints(1000));
        $this->assertSame(EliteTier::Silver,   EliteTier::fromPoints(4999));
        $this->assertSame(EliteTier::Gold,     EliteTier::fromPoints(5000));
        $this->assertSame(EliteTier::Gold,     EliteTier::fromPoints(24999));
        $this->assertSame(EliteTier::Platinum, EliteTier::fromPoints(25000));
        $this->assertSame(EliteTier::Platinum, EliteTier::fromPoints(999999));
    }

    /** @test */
    public function test_elite_tier_next_and_progress(): void
    {
        $this->assertSame(EliteTier::Silver,   EliteTier::Bronze->next());
        $this->assertSame(EliteTier::Gold,     EliteTier::Silver->next());
        $this->assertSame(EliteTier::Platinum, EliteTier::Gold->next());
        $this->assertNull(EliteTier::Platinum->next());

        // Progress at Platinum is always 100
        $this->assertSame(100, EliteTier::Platinum->progressPct(50000));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. Auto-referral prevention (UserAuthService)
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_self_referral_is_prevented_at_registration(): void
    {
        // Create a user with a known referral code.
        $existingUser = $this->makeUser('alice@nexu.com');

        $authService = new UserAuthService();

        // resolveReferrer is private — invoke via Reflection to test the
        // self-referral guard directly without triggering the unique-email DB constraint.
        $method = new \ReflectionMethod(UserAuthService::class, 'resolveReferrer');

        // Same email as the code owner → should throw ValidationException (self-referral blocked).
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('No puedes utilizar tu propio código de referido para registrarte.');
        
        $method->invoke($authService, $existingUser->referral_code, 'alice@nexu.com');

        // Different email → should resolve the referrer normally.
        $other = $method->invoke($authService, $existingUser->referral_code, 'bob@nexu.com');
        $this->assertNotNull($other);
        $this->assertTrue($other->is($existingUser));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. getSummary returns correct structure
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_get_summary_returns_expected_structure(): void
    {
        $referrer = $this->makeUser();
        $this->makeWallet($referrer, 0.0);

        $summary = $this->service->getSummary($referrer);

        $this->assertArrayHasKey('code',            $summary);
        $this->assertArrayHasKey('share_url',       $summary);
        $this->assertArrayHasKey('commission_rate', $summary);
        $this->assertArrayHasKey('stats',           $summary);
        $this->assertArrayHasKey('elite',           $summary);

        $this->assertArrayHasKey('active_count',   $summary['stats']);
        $this->assertArrayHasKey('inactive_count', $summary['stats']);
        $this->assertArrayHasKey('total_earned',   $summary['stats']);

        $this->assertArrayHasKey('points',         $summary['elite']);
        $this->assertArrayHasKey('tier',           $summary['elite']);
        $this->assertArrayHasKey('next_tier',      $summary['elite']);
        $this->assertArrayHasKey('progress_pct',   $summary['elite']);

        $this->assertSame($referrer->referral_code, $summary['code']);
        $this->assertSame('bronze', $summary['elite']['tier']);
    }
}
