<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ElitePoint;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(string $email = null): User
    {
        $user = User::create([
            'name'              => fake()->name(),
            'email'             => $email ?? fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => '0.00000000',
            'balance_in_operation' => '0.00000000',
            'balance_total'        => '0.00000000',
        ]);

        return $user;
    }

    private function makeReferral(User $referrer, User $referred, float $rate = 0.05): Referral
    {
        return Referral::create([
            'referrer_id'     => $referrer->id,
            'referred_id'     => $referred->id,
            'commission_rate' => $rate,
            'total_earned'    => '0.00000000',
        ]);
    }

    private function makeCommissionTx(User $referrer, User $referred, float $amount = 5.0): Transaction
    {
        return Transaction::create([
            'user_id'    => $referrer->id,
            'type'       => 'referral_commission',
            'amount'     => $amount,
            'fee_amount' => '0.00000000',
            'net_amount' => $amount,
            'currency'   => 'USDT',
            'status'     => 'confirmed',
            'metadata'   => [
                'source_deposit_id' => Str::uuid()->toString(),
                'source_user_id'    => $referred->id,
                'commission_rate'   => '0.05000000',
                'source_net_amount' => '100.00000000',
            ],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/v1/referrals/summary
    // ══════════════════════════════════════════════════════════════════════════

    public function test_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/referrals/summary');
        $response->assertStatus(401);
    }

    public function test_summary_returns_correct_structure(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/referrals/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'code',
                    'share_url',
                    'commission_rate',
                    'stats' => ['active_count', 'inactive_count', 'total_earned'],
                    'elite' => ['points', 'tier', 'next_tier', 'points_to_next', 'progress_pct'],
                ],
            ]);
    }

    public function test_summary_reflects_referral_data(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        Sanctum::actingAs($referrer, ['*'], 'api');

        $referral = $this->makeReferral($referrer, $referred, 0.07);
        $referral->update(['total_earned' => '7.00000000']);

        ElitePoint::create([
            'user_id'     => $referrer->id,
            'points'      => '100.00',
            'description' => 'Test points',
        ]);

        $response = $this->getJson('/api/v1/referrals/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.code', $referrer->referral_code)
            ->assertJsonPath('data.commission_rate', '0.0700')
            ->assertJsonPath('data.elite.tier', 'bronze');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/v1/referrals/network
    // ══════════════════════════════════════════════════════════════════════════

    public function test_network_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/referrals/network');
        $response->assertStatus(401);
    }

    public function test_network_returns_paginated_referrals(): void
    {
        $referrer = $this->makeUser();
        Sanctum::actingAs($referrer, ['*'], 'api');

        $referred1 = $this->makeUser();
        $referred2 = $this->makeUser();
        $this->makeReferral($referrer, $referred1);
        $this->makeReferral($referrer, $referred2);

        $response = $this->getJson('/api/v1/referrals/network');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'masked_email', 'joined_at', 'status', 'total_generated'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_network_returns_empty_for_user_with_no_referrals(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/referrals/network');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_network_active_status_is_correct(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        Sanctum::actingAs($referrer, ['*'], 'api');

        $this->makeReferral($referrer, $referred);

        // Referred has no confirmed deposit yet → inactive
        $response = $this->getJson('/api/v1/referrals/network');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'inactive');

        // Now simulate a confirmed deposit
        Transaction::create([
            'user_id'    => $referred->id,
            'type'       => 'deposit',
            'amount'     => '100.00000000',
            'fee_amount' => '0.00000000',
            'net_amount' => '100.00000000',
            'currency'   => 'USDT',
            'status'     => 'confirmed',
        ]);

        $response = $this->getJson('/api/v1/referrals/network');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_network_emails_are_masked(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser('johndoe@example.com');
        Sanctum::actingAs($referrer, ['*'], 'api');

        $this->makeReferral($referrer, $referred);

        $response = $this->getJson('/api/v1/referrals/network');

        $maskedEmail = $response->json('data.0.masked_email');
        $this->assertStringNotContainsString('johndoe', $maskedEmail);
        $this->assertStringContainsString('@example.com', $maskedEmail);
        $this->assertStringContainsString('***', $maskedEmail);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/v1/referrals/earnings
    // ══════════════════════════════════════════════════════════════════════════

    public function test_earnings_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/referrals/earnings');
        $response->assertStatus(401);
    }

    public function test_earnings_returns_paginated_commission_transactions(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser();
        Sanctum::actingAs($referrer, ['*'], 'api');

        $this->makeCommissionTx($referrer, $referred, 5.0);
        $this->makeCommissionTx($referrer, $referred, 7.5);

        $response = $this->getJson('/api/v1/referrals/earnings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'amount', 'source_user_masked', 'created_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_earnings_masks_source_user_email(): void
    {
        $referrer = $this->makeUser();
        $referred = $this->makeUser('jane@example.com');
        Sanctum::actingAs($referrer, ['*'], 'api');

        $this->makeCommissionTx($referrer, $referred, 5.0);

        $response = $this->getJson('/api/v1/referrals/earnings');

        $sourceMasked = $response->json('data.0.source_user_masked');
        $this->assertStringNotContainsString('jane', $sourceMasked);
        $this->assertStringContainsString('***', $sourceMasked);
    }

    public function test_earnings_returns_empty_when_no_commissions(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/referrals/earnings');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /api/v1/auth/validate-referral-code (public)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_validate_code_returns_valid_true_for_existing_code(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/auth/validate-referral-code', [
            'code' => $user->referral_code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonStructure(['data' => ['valid', 'referrer_name']]);

        $this->assertNotNull($response->json('data.referrer_name'));
    }

    public function test_validate_code_returns_valid_false_for_unknown_code(): void
    {
        $response = $this->postJson('/api/v1/auth/validate-referral-code', [
            'code' => 'NOTEXIST',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.referrer_name', null);
    }

    public function test_validate_code_is_case_insensitive(): void
    {
        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/auth/validate-referral-code', [
            'code' => strtolower($user->referral_code),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true);
    }

    public function test_validate_code_requires_code_field(): void
    {
        $response = $this->postJson('/api/v1/auth/validate-referral-code', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_validate_code_is_publicly_accessible_without_auth(): void
    {
        $user = $this->makeUser();

        // No Sanctum::actingAs — should still work
        $response = $this->postJson('/api/v1/auth/validate-referral-code', [
            'code' => $user->referral_code,
        ]);

        $response->assertStatus(200);
    }
}
