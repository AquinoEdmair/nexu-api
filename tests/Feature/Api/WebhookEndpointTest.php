<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Events\DepositConfirmed;
use App\Jobs\ProcessDepositWebhook;
use App\Models\CommissionConfig;
use App\Models\Admin;
use App\Models\DepositInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'test-webhook-secret-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.crypto.webhook_secret' => $this->webhookSecret]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        return User::create([
            'name'              => 'Test User',
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function makeWallet(User $user, string $available = '0.00000000', string $inOperation = '0.00000000'): Wallet
    {
        $total = bcadd($available, $inOperation, 8);

        return Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => $available,
            'balance_in_operation' => $inOperation,
            'balance_total'        => $total,
        ]);
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

    private function makeAdmin(): Admin
    {
        return Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin-' . Str::random(5) . '@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);
    }

    private function signPayload(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), $this->webhookSecret);
    }

    private function postWebhook(array $payload, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        $json = json_encode($payload);
        $sig  = $signature ?? $this->signPayload($payload);

        return $this->call(
            'POST',
            '/api/v1/webhook/deposit',
            [],
            [],
            [],
            [
                'HTTP_X-Webhook-Signature' => $sig,
                'HTTP_ACCEPT'              => 'application/json',
                'CONTENT_TYPE'             => 'application/json',
            ],
            $json,
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Signature verification
    // ══════════════════════════════════════════════════════════════════════════

    public function test_webhook_returns_200_with_valid_signature(): void
    {
        Bus::fake([ProcessDepositWebhook::class]);

        $user    = $this->makeUser();
        $invoice = $this->makeInvoice($user);

        $payload = [
            'invoice_id' => $invoice->invoice_id,
            'status'     => 'confirmed',
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-VALID-SIG',
        ];

        $this->postWebhook($payload)
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        Bus::assertDispatched(ProcessDepositWebhook::class);
    }

    public function test_webhook_returns_403_with_invalid_signature(): void
    {
        $payload = [
            'invoice_id' => 'INV-FAKE',
            'status'     => 'confirmed',
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-BAD-SIG',
        ];

        $this->postWebhook($payload, 'invalid-signature-here')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Invalid signature.');
    }

    public function test_webhook_returns_500_when_secret_not_configured(): void
    {
        config(['services.crypto.webhook_secret' => '']);

        $payload = [
            'invoice_id' => 'INV-NO-SECRET',
            'status'     => 'confirmed',
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-NO-SECRET',
        ];

        $this->postWebhook($payload, 'any-sig')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Webhook secret not configured.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Non-confirmed status ignored
    // ══════════════════════════════════════════════════════════════════════════

    public function test_webhook_ignores_non_confirmed_status(): void
    {
        Bus::fake([ProcessDepositWebhook::class]);

        $payload = [
            'invoice_id' => 'INV-PENDING',
            'status'     => 'pending',
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-PENDING',
        ];

        $this->postWebhook($payload)
            ->assertStatus(200)
            ->assertJsonPath('status', 'ignored');

        Bus::assertNotDispatched(ProcessDepositWebhook::class);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Payload validation
    // ══════════════════════════════════════════════════════════════════════════

    public function test_webhook_returns_422_for_missing_fields(): void
    {
        $payload = ['invoice_id' => 'INV-INCOMPLETE'];

        $this->postWebhook($payload)
            ->assertStatus(422);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Full processing integration (sync via Bus::dispatchSync)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_full_processing_creates_transaction_and_updates_balance(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, '500.00000000', '100.00000000');
        $invoice = $this->makeInvoice($user);

        $admin = $this->makeAdmin();
        CommissionConfig::create([
            'type'       => 'deposit',
            'value'      => '2.0000',
            'is_active'  => true,
            'created_by' => $admin->id,
        ]);

        $payload = [
            'invoice_id' => $invoice->invoice_id,
            'status'     => 'confirmed',
            'amount'     => '200.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-FULL-PROCESS',
        ];

        // Dispatch synchronously to test full flow
        $job = new ProcessDepositWebhook($payload);
        $job->handle(app(\App\Services\DepositService::class));

        // Transaction created
        $depositTx = Transaction::where('external_tx_id', 'TX-FULL-PROCESS')->first();
        $this->assertNotNull($depositTx);
        $this->assertEquals('deposit', $depositTx->type);
        $this->assertEquals('200.00000000', $depositTx->amount);
        // fee = 200 * 0.02 = 4, net = 196
        $this->assertEquals('4.00000000', $depositTx->fee_amount);
        $this->assertEquals('196.00000000', $depositTx->net_amount);

        // Commission transaction created
        $commTx = Transaction::where('user_id', $user->id)->where('type', 'commission')->first();
        $this->assertNotNull($commTx);
        $this->assertEquals('4.00000000', $commTx->amount);

        // Wallet updated
        $wallet->refresh();
        // in_operation = 100 + 196 = 296
        $this->assertEquals('296.00000000', $wallet->balance_in_operation);
        // available unchanged
        $this->assertEquals('500.00000000', $wallet->balance_available);
        // total = 500 + 296 = 796
        $this->assertEquals('796.00000000', $wallet->balance_total);

        // Invoice marked completed
        $invoice->refresh();
        $this->assertEquals('completed', $invoice->status);
        $this->assertNotNull($invoice->completed_at);

        // Event dispatched
        Event::assertDispatched(DepositConfirmed::class);
    }

    public function test_duplicate_webhook_does_not_double_deposit(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, '0.00000000', '0.00000000');
        $invoice = $this->makeInvoice($user);

        $payload = [
            'invoice_id' => $invoice->invoice_id,
            'status'     => 'confirmed',
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-DUPLICATE-CHECK',
        ];

        $depositService = app(\App\Services\DepositService::class);

        // First call
        $depositService->processWebhook($payload);

        $balanceAfterFirst = Wallet::where('user_id', $user->id)->value('balance_total');
        $txCountAfterFirst = Transaction::where('user_id', $user->id)->count();

        // Second call — must be idempotent
        $depositService->processWebhook($payload);

        $balanceAfterSecond = Wallet::where('user_id', $user->id)->value('balance_total');
        $txCountAfterSecond = Transaction::where('user_id', $user->id)->count();

        $this->assertEquals($balanceAfterFirst, $balanceAfterSecond, 'CRITICAL: Balance changed on duplicate webhook — double deposit detected!');
        $this->assertEquals($txCountAfterFirst, $txCountAfterSecond, 'CRITICAL: Extra transactions created on duplicate webhook!');
    }
}
