<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionEndpointTest extends TestCase
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
            'balance_in_operation' => '0.00000000',
            'balance_total'        => '1000.00000000',
        ]);

        return $user;
    }

    private function makeTransaction(User $user, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'amount'     => '100.00000000',
            'fee_amount' => '2.00000000',
            'net_amount' => '98.00000000',
            'currency'   => 'USDT',
            'status'     => 'confirmed',
            'notes'      => 'Test deposit',
        ], $overrides));
    }

    // ── GET /api/v1/transactions ────────────────────────────────────────────

    public function test_transactions_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/transactions')
            ->assertStatus(401);
    }

    public function test_transactions_returns_403_when_user_is_blocked(): void
    {
        $user = $this->makeUser('blocked');
        Sanctum::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/transactions')
            ->assertStatus(403);
    }

    public function test_transactions_returns_200_with_paginated_data(): void
    {
        $user = $this->makeUser();
        $this->makeTransaction($user);
        $this->makeTransaction($user, ['type' => 'withdrawal', 'status' => 'pending']);
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/transactions')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_transactions_only_returns_own_transactions(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        $this->makeTransaction($user1);
        $this->makeTransaction($user1);
        $this->makeTransaction($user2); // should NOT appear

        Sanctum::actingAs($user1, ['*'], 'api');

        $response = $this->getJson('/api/v1/transactions')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_transactions_filters_by_type(): void
    {
        $user = $this->makeUser();
        $this->makeTransaction($user, ['type' => 'deposit']);
        $this->makeTransaction($user, ['type' => 'withdrawal']);
        $this->makeTransaction($user, ['type' => 'yield']);
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/transactions?type=deposit')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_transactions_respects_per_page_limit(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 5; $i++) {
            $this->makeTransaction($user);
        }
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/transactions?per_page=2')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_transactions_caps_per_page_at_50(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/transactions?per_page=999')
            ->assertStatus(200);

        $this->assertEquals(50, $response->json('meta.per_page'));
    }

    // ── GET /api/v1/transactions/{id} ───────────────────────────────────────

    public function test_transaction_show_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/transactions/' . Str::uuid())
            ->assertStatus(401);
    }

    public function test_transaction_show_returns_own_transaction(): void
    {
        $user = $this->makeUser();
        $tx = $this->makeTransaction($user, ['amount' => '250.00000000']);
        Sanctum::actingAs($user, ['*'], 'api');

        $this->getJson("/api/v1/transactions/{$tx->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $tx->id);
    }

    public function test_transaction_show_returns_404_for_other_users_transaction(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();
        $tx = $this->makeTransaction($user2);

        Sanctum::actingAs($user1, ['*'], 'api');

        $this->getJson("/api/v1/transactions/{$tx->id}")
            ->assertStatus(404);
    }

    public function test_transaction_show_returns_404_for_nonexistent_id(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/transactions/' . Str::uuid())
            ->assertStatus(404);
    }
}
