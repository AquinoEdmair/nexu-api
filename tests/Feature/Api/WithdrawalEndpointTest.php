<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeUser(string $status = 'active'): User
    {
        $user = User::create([
            'name'              => 'Test User',
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => $status,
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => '1000.00000000',
            'balance_in_operation' => '500.00000000',
            'balance_total'        => '1500.00000000',
        ]);

        return $user;
    }

    private function makeWithdrawal(User $user, array $overrides = []): WithdrawalRequest
    {
        return WithdrawalRequest::create(array_merge([
            'user_id'             => $user->id,
            'amount'              => '100.00000000',
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
            'status'              => 'pending',
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /api/v1/withdrawals
    // ══════════════════════════════════════════════════════════════════════════

    public function test_store_returns_201_with_valid_request(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->postJson('/api/v1/withdrawals', [
            'amount'              => 100,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'amount', 'currency', 'destination_address', 'status', 'created_at'],
            ])
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_store_reserves_funds_from_balance_available(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 300,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(201);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEquals('700.00000000', $wallet->balance_available);
        $this->assertEquals('500.00000000', $wallet->balance_in_operation);
        $this->assertEquals('1200.00000000', $wallet->balance_total);
    }

    public function test_store_returns_422_when_insufficient_balance(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 2000,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_store_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 100,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(401);
    }

    public function test_store_returns_403_when_user_is_blocked(): void
    {
        $user = $this->makeUser('blocked');
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 100,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(403);
    }

    public function test_store_returns_422_for_invalid_currency(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 100,
            'currency'            => 'DOGE',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_store_returns_422_for_negative_amount(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => -50,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_returns_422_for_zero_amount(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 0,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_returns_422_for_short_address(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 100,
            'currency'            => 'USDT',
            'destination_address' => 'short',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['destination_address']);
    }

    public function test_store_returns_422_for_missing_fields(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'currency', 'destination_address']);
    }

    public function test_store_allows_exact_balance_withdrawal(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 1000,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(201);

        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $wallet->balance_available, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/v1/withdrawals
    // ══════════════════════════════════════════════════════════════════════════

    public function test_index_returns_200_with_paginated_data(): void
    {
        $user = $this->makeUser();
        $this->makeWithdrawal($user);
        $this->makeWithdrawal($user, ['status' => 'completed']);
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/withdrawals')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/withdrawals')
            ->assertStatus(401);
    }

    public function test_index_only_returns_own_withdrawals(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        $this->makeWithdrawal($user1);
        $this->makeWithdrawal($user1);
        $this->makeWithdrawal($user2);

        Sanctum::actingAs($user1, ['*'], 'api');

        $response = $this->getJson('/api/v1/withdrawals')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_index_filters_by_status(): void
    {
        $user = $this->makeUser();
        $this->makeWithdrawal($user, ['status' => 'pending']);
        $this->makeWithdrawal($user, ['status' => 'completed']);
        $this->makeWithdrawal($user, ['status' => 'rejected']);

        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/withdrawals?status=pending')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_index_respects_per_page(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 5; $i++) {
            $this->makeWithdrawal($user);
        }
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/withdrawals?per_page=2')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_index_caps_per_page_at_50(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/withdrawals?per_page=999')
            ->assertStatus(200);

        $this->assertEquals(50, $response->json('meta.per_page'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DELETE /api/v1/withdrawals/{id}
    // ══════════════════════════════════════════════════════════════════════════

    public function test_destroy_cancels_pending_withdrawal(): void
    {
        $user = $this->makeUser();
        $withdrawal = $this->makeWithdrawal($user, ['status' => 'pending', 'amount' => '200.00000000']);
        Sanctum::actingAs($user, ['*'], 'api');

        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Solicitud de retiro cancelada.');

        $this->assertEquals('rejected', $withdrawal->fresh()->status);
    }

    public function test_destroy_releases_funds_back_to_wallet(): void
    {
        $user = $this->makeUser();
        $withdrawal = $this->makeWithdrawal($user, ['status' => 'pending', 'amount' => '200.00000000']);
        Sanctum::actingAs($user, ['*'], 'api');

        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(200);

        $wallet = Wallet::where('user_id', $user->id)->first();
        // Original: 1000, cancel returns 200 → 1200
        $this->assertEquals('1200.00000000', $wallet->balance_available);
        $this->assertEquals('1700.00000000', $wallet->balance_total);
    }

    public function test_destroy_returns_409_when_not_pending(): void
    {
        $user = $this->makeUser();
        $withdrawal = $this->makeWithdrawal($user, ['status' => 'approved']);
        Sanctum::actingAs($user, ['*'], 'api');

        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'No se puede cancelar: el retiro ya no está en estado pendiente.');
    }

    public function test_destroy_returns_403_when_other_user(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();
        $withdrawal = $this->makeWithdrawal($user1);

        Sanctum::actingAs($user2, ['*'], 'api');

        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(403);
    }

    public function test_destroy_returns_401_when_unauthenticated(): void
    {
        $this->deleteJson('/api/v1/withdrawals/' . Str::uuid())
            ->assertStatus(401);
    }

    public function test_destroy_returns_404_for_nonexistent_id(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->deleteJson('/api/v1/withdrawals/' . Str::uuid())
            ->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CRITICAL: Double cancel idempotency
    // ══════════════════════════════════════════════════════════════════════════

    public function test_double_cancel_does_not_double_refund(): void
    {
        $user = $this->makeUser();
        $withdrawal = $this->makeWithdrawal($user, ['status' => 'pending', 'amount' => '200.00000000']);
        Sanctum::actingAs($user, ['*'], 'api');

        // First cancel — succeeds
        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(200);

        $balanceAfterFirst = Wallet::where('user_id', $user->id)->value('balance_available');

        // Second cancel — should return 409
        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(409);

        $balanceAfterSecond = Wallet::where('user_id', $user->id)->value('balance_available');

        // Balance unchanged — no double refund
        $this->assertEquals($balanceAfterFirst, $balanceAfterSecond, 'CRITICAL: Double refund detected on second cancel!');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CRITICAL: Balance never goes negative
    // ══════════════════════════════════════════════════════════════════════════

    public function test_balance_never_goes_negative(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        // First withdrawal takes all available (1000)
        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 1000,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(201);

        // Second withdrawal should fail — no more balance_available
        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 1,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(422);

        $wallet = Wallet::where('user_id', $user->id)->first();

        // balance_available must be exactly 0, never negative
        $this->assertGreaterThanOrEqual(0, (float) $wallet->balance_available, 'CRITICAL: Negative balance detected!');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CRITICAL: balance_total invariant
    // ══════════════════════════════════════════════════════════════════════════

    public function test_balance_total_equals_available_plus_in_operation_after_withdrawal(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/withdrawals', [
            'amount'              => 300,
            'currency'            => 'USDT',
            'destination_address' => 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
        ])->assertStatus(201);

        $wallet = Wallet::where('user_id', $user->id)->first();

        $expectedTotal = round((float) $wallet->balance_available + (float) $wallet->balance_in_operation, 8);
        $this->assertEqualsWithDelta($expectedTotal, (float) $wallet->balance_total, 0.000001,
            'CRITICAL: balance_total != available + in_operation');
    }

    public function test_balance_total_invariant_after_cancel(): void
    {
        $user = $this->makeUser();
        $withdrawal = $this->makeWithdrawal($user, ['status' => 'pending', 'amount' => '200.00000000']);
        Sanctum::actingAs($user, ['*'], 'api');

        $this->deleteJson("/api/v1/withdrawals/{$withdrawal->id}")
            ->assertStatus(200);

        $wallet = Wallet::where('user_id', $user->id)->first();

        $expectedTotal = round((float) $wallet->balance_available + (float) $wallet->balance_in_operation, 8);
        $this->assertEqualsWithDelta($expectedTotal, (float) $wallet->balance_total, 0.000001,
            'CRITICAL: balance_total != available + in_operation after cancel');
    }
}
