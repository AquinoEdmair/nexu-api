<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\CryptoProviderInterface;
use App\DTOs\CryptoInvoiceDTO;
use App\Models\DepositInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepositEndpointTest extends TestCase
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

    private function makeDeposit(User $user, array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'amount'     => '100.00000000',
            'fee_amount' => '2.50000000',
            'net_amount' => '97.50000000',
            'currency'   => 'USDT',
            'status'     => 'confirmed',
        ], $overrides));
    }

    private function makeInvoice(User $user, array $overrides = []): DepositInvoice
    {
        return DepositInvoice::create(array_merge([
            'user_id'    => $user->id,
            'invoice_id' => 'INV-' . Str::random(16),
            'currency'   => 'USDT',
            'network'    => 'TRC20',
            'address'    => 'T' . Str::random(33),
            'status'     => 'awaiting_payment',
            'expires_at' => now()->addHours(24),
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // POST /api/v1/deposits/initiate
    // ══════════════════════════════════════════════════════════════════════════

    public function test_initiate_returns_201_with_invoice_data(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $mock = $this->createMock(CryptoProviderInterface::class);
        $mock->method('createInvoice')
            ->willReturn(new CryptoInvoiceDTO(
                invoiceId: 'INV-ENDPOINT-TEST',
                address:   'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                currency:  'USDT',
                network:   'TRC20',
                qrCodeUrl: null,
                expiresAt: Carbon::now()->addHours(24),
            ));
        $this->app->instance(CryptoProviderInterface::class, $mock);

        $response = $this->postJson('/api/v1/deposits/initiate', ['currency' => 'USDT']);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['invoice_id', 'address', 'currency', 'network', 'qr_code_url', 'expires_at'],
            ])
            ->assertJsonPath('data.invoice_id', 'INV-ENDPOINT-TEST')
            ->assertJsonPath('data.currency', 'USDT');
    }

    public function test_initiate_returns_401_when_unauthenticated(): void
    {
        $this->postJson('/api/v1/deposits/initiate', ['currency' => 'USDT'])
            ->assertStatus(401);
    }

    public function test_initiate_returns_403_when_user_is_blocked(): void
    {
        $user = $this->makeUser('blocked');
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/deposits/initiate', ['currency' => 'USDT'])
            ->assertStatus(403);
    }

    public function test_initiate_returns_422_for_invalid_currency(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/deposits/initiate', ['currency' => 'DOGE'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_initiate_returns_422_when_currency_missing(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/deposits/initiate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_initiate_returns_502_when_provider_fails(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*'], 'api');

        $mock = $this->createMock(CryptoProviderInterface::class);
        $mock->method('createInvoice')
            ->willThrowException(new \RuntimeException('Provider down'));
        $this->app->instance(CryptoProviderInterface::class, $mock);

        $this->postJson('/api/v1/deposits/initiate', ['currency' => 'USDT'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Could not create deposit address. Please try again later.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/v1/deposits
    // ══════════════════════════════════════════════════════════════════════════

    public function test_deposits_index_returns_200_with_paginated_data(): void
    {
        $user = $this->makeUser();
        $this->makeDeposit($user);
        $this->makeDeposit($user);
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/deposits');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_deposits_index_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/deposits')
            ->assertStatus(401);
    }

    public function test_deposits_index_only_returns_own_deposits(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        $this->makeDeposit($user1);
        $this->makeDeposit($user2); // should NOT appear

        Sanctum::actingAs($user1, ['*'], 'api');

        $response = $this->getJson('/api/v1/deposits')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_deposits_index_only_returns_deposit_type(): void
    {
        $user = $this->makeUser();
        $this->makeDeposit($user, ['type' => 'deposit']);
        $this->makeDeposit($user, ['type' => 'withdrawal']);

        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/deposits')
            ->assertStatus(200);

        // The controller filters by type=deposit, so withdrawal should not appear
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_deposits_index_respects_per_page(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 5; $i++) {
            $this->makeDeposit($user);
        }
        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/deposits?per_page=2')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.total'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /api/v1/deposits/pending
    // ══════════════════════════════════════════════════════════════════════════

    public function test_pending_returns_200_with_active_invoices(): void
    {
        $user = $this->makeUser();

        // Active
        $this->makeInvoice($user, ['expires_at' => now()->addHours(10)]);
        // Expired (should not appear)
        $this->makeInvoice($user, ['expires_at' => now()->subMinutes(5)]);
        // Completed (should not appear)
        $this->makeInvoice($user, ['status' => 'completed', 'expires_at' => now()->addHours(10)]);

        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/deposits/pending')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['invoice_id', 'address', 'currency', 'network', 'status', 'expires_at', 'created_at']],
            ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_pending_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/deposits/pending')
            ->assertStatus(401);
    }

    public function test_pending_only_returns_own_invoices(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        $this->makeInvoice($user1);
        $this->makeInvoice($user2);

        Sanctum::actingAs($user1, ['*'], 'api');

        $response = $this->getJson('/api/v1/deposits/pending')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }
}
