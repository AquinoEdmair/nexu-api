<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BalanceSnapshot;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BalanceEndpointTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeUser(string $status = 'active'): User
    {
        return User::create([
            'name'              => 'Test User',
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => $status,
            'email_verified_at' => now(),
        ]);
    }

    private function makeWallet(User $user, string $available = '1000.00000000', string $inOperation = '5000.00000000'): Wallet
    {
        return Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => bcadd($available, $inOperation, 8),
        ]);
    }

    // ── GET /api/v1/balance ─────────────────────────────────────────────────

    public function test_balance_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/balance')
            ->assertStatus(401);
    }

    public function test_balance_returns_403_when_user_is_blocked(): void
    {
        $user = $this->makeUser('blocked');
        Sanctum::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/balance')
            ->assertStatus(403);
    }

    public function test_balance_returns_200_with_wallet_data(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, '1234.56780000', '8765.43220000');
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/balance')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'balance_available',
                    'balance_in_operation',
                    'balance_total',
                    'currency',
                ],
            ]);

        $data = $response->json('data');

        $this->assertEquals('1234.56780000', $data['balance_available']);
        $this->assertEquals('8765.43220000', $data['balance_in_operation']);
        $this->assertEquals('USD', $data['currency']);
    }

    public function test_balance_total_equals_available_plus_in_operation(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user, '3333.33330000', '6666.66670000');
        Sanctum::actingAs($user, ['*'], 'api');

        $data = $this->getJson('/api/v1/balance')
            ->assertStatus(200)
            ->json('data');

        $expected = bcadd($data['balance_available'], $data['balance_in_operation'], 8);
        $this->assertEquals($expected, $data['balance_total']);
    }

    public function test_balance_returns_zeros_without_wallet(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $data = $this->getJson('/api/v1/balance')
            ->assertStatus(200)
            ->json('data');

        $this->assertEquals('0.00000000', $data['balance_available']);
        $this->assertEquals('0.00000000', $data['balance_in_operation']);
        $this->assertEquals('0.00000000', $data['balance_total']);
    }

    // ── GET /api/v1/balance/history ─────────────────────────────────────────

    public function test_balance_history_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/balance/history')
            ->assertStatus(401);
    }

    public function test_balance_history_returns_403_when_user_is_blocked(): void
    {
        $user = $this->makeUser('blocked');
        Sanctum::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/balance/history')
            ->assertStatus(403);
    }

    public function test_balance_history_returns_200_with_snapshots(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user);
        Sanctum::actingAs($user, ['*'], 'api');

        BalanceSnapshot::create([
            'user_id'              => $user->id,
            'balance_available'    => '500.00000000',
            'balance_in_operation' => '500.00000000',
            'balance_total'        => '1000.00000000',
            'snapshot_date'        => Carbon::today()->subDay()->toDateString(),
            'created_at'           => now(),
        ]);

        $response = $this->getJson('/api/v1/balance/history?days=30')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['date', 'balance_total', 'balance_available', 'balance_in_operation'],
                ],
            ]);

        // 1 snapshot + today's live data = 2
        $this->assertCount(2, $response->json('data'));
    }

    public function test_balance_history_defaults_to_30_days(): void
    {
        $user = $this->makeUser();
        $this->makeWallet($user);
        Sanctum::actingAs($user, ['*'], 'api');

        // Snapshot 60 days ago — should NOT appear with default 30
        BalanceSnapshot::create([
            'user_id'              => $user->id,
            'balance_available'    => '100.00000000',
            'balance_in_operation' => '100.00000000',
            'balance_total'        => '200.00000000',
            'snapshot_date'        => Carbon::today()->subDays(60)->toDateString(),
            'created_at'           => now(),
        ]);

        $data = $this->getJson('/api/v1/balance/history')
            ->assertStatus(200)
            ->json('data');

        // Only today's live data — the 60-day-old snapshot is out of range
        $this->assertCount(1, $data);
    }

    public function test_balance_history_empty_without_wallet(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $data = $this->getJson('/api/v1/balance/history')
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(0, $data);
    }
}
