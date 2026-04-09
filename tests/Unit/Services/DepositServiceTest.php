<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Contracts\CryptoProviderInterface;
use App\DTOs\CryptoInvoiceDTO;
use App\Events\DepositConfirmed;
use App\Models\CommissionConfig;
use App\Models\Admin;
use App\Models\DepositInvoice;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\CommissionService;
use App\Services\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DepositServiceTest extends TestCase
{
    use RefreshDatabase;

    private DepositService $service;
    private CryptoProviderInterface $cryptoProvider;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cryptoProvider = $this->createMock(CryptoProviderInterface::class);
        $commissionService    = new CommissionService();
        $this->service        = new DepositService($this->cryptoProvider, $commissionService);

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

    private function makeCommission(float $value = 2.5): void
    {
        CommissionConfig::create([
            'type'       => 'deposit',
            'value'      => $value,
            'is_active'  => true,
            'created_by' => $this->admin->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. initiateDeposit
    // ══════════════════════════════════════════════════════════════════════════

    public function test_initiate_deposit_creates_invoice(): void
    {
        $user = $this->makeUser();

        $this->cryptoProvider
            ->method('createInvoice')
            ->willReturn(new CryptoInvoiceDTO(
                invoiceId: 'INV-TEST123',
                address:   'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                currency:  'USDT',
                network:   'TRC20',
                qrCodeUrl: null,
                expiresAt: Carbon::now()->addHours(24),
            ));

        $invoice = $this->service->initiateDeposit($user, 'USDT');

        $this->assertInstanceOf(DepositInvoice::class, $invoice);
        $this->assertEquals('INV-TEST123', $invoice->invoice_id);
        $this->assertEquals('USDT', $invoice->currency);
        $this->assertEquals('TRC20', $invoice->network);
        $this->assertEquals('awaiting_payment', $invoice->status);
        $this->assertDatabaseHas('deposit_invoices', ['invoice_id' => 'INV-TEST123']);
    }

    public function test_initiate_deposit_propagates_runtime_exception(): void
    {
        $user = $this->makeUser();

        $this->cryptoProvider
            ->method('createInvoice')
            ->willThrowException(new \RuntimeException('Provider unavailable'));

        $this->expectException(\RuntimeException::class);
        $this->service->initiateDeposit($user, 'USDT');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. processWebhook — happy path
    // ══════════════════════════════════════════════════════════════════════════

    public function test_process_webhook_creates_deposit_transaction(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user, '500.00000000', '0.00000000');
        $invoice = $this->makeInvoice($user);
        $this->makeCommission(2.5);

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-' . Str::random(20),
        ]);

        // Transaction created
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type'    => 'deposit',
            'amount'  => '100.00000000',
            'status'  => 'confirmed',
        ]);

        // Event dispatched
        Event::assertDispatched(DepositConfirmed::class, function (DepositConfirmed $event) use ($user) {
            return $event->user->id === $user->id && $event->currency === 'USDT';
        });
    }

    public function test_process_webhook_updates_balance_in_operation(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, '500.00000000', '200.00000000');
        $invoice = $this->makeInvoice($user);
        $this->makeCommission(2.5);

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-' . Str::random(20),
        ]);

        $wallet->refresh();

        // fee = 100 * 0.025 = 2.5, net = 97.5
        $this->assertEquals('297.50000000', $wallet->balance_in_operation);
        // balance_available unchanged
        $this->assertEquals('500.00000000', $wallet->balance_available);
        // total = available + in_operation = 500 + 297.5 = 797.5
        $this->assertEquals('797.50000000', $wallet->balance_total);
    }

    public function test_process_webhook_calculates_commission_correctly(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user);
        $invoice = $this->makeInvoice($user);
        $this->makeCommission(5.0); // 5%

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '200.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-' . Str::random(20),
        ]);

        // fee = 200 * 0.05 = 10, net = 190
        $depositTx = Transaction::where('user_id', $user->id)->where('type', 'deposit')->first();
        $this->assertEquals('10.00000000', $depositTx->fee_amount);
        $this->assertEquals('190.00000000', $depositTx->net_amount);

        // Commission transaction created
        $commissionTx = Transaction::where('user_id', $user->id)->where('type', 'commission')->first();
        $this->assertNotNull($commissionTx);
        $this->assertEquals('10.00000000', $commissionTx->amount);
    }

    public function test_process_webhook_no_commission_tx_when_fee_is_zero(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user);
        $invoice = $this->makeInvoice($user);
        // No commission config → rate = 0

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-' . Str::random(20),
        ]);

        // Deposit transaction exists
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type'    => 'deposit',
        ]);

        // No commission transaction
        $this->assertDatabaseMissing('transactions', [
            'user_id' => $user->id,
            'type'    => 'commission',
        ]);

        // Full amount credited
        $wallet = Wallet::where('user_id', $user->id)->first();
        $this->assertEquals('100.00000000', $wallet->balance_in_operation);
    }

    public function test_process_webhook_marks_invoice_completed(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user);
        $invoice = $this->makeInvoice($user);

        $txHash = 'TX-COMPLETED-' . Str::random(10);

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '50.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => $txHash,
        ]);

        $invoice->refresh();

        $this->assertEquals('completed', $invoice->status);
        $this->assertEquals('50.00000000', $invoice->amount_received);
        $this->assertEquals($txHash, $invoice->tx_hash);
        $this->assertNotNull($invoice->transaction_id);
        $this->assertNotNull($invoice->completed_at);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. processWebhook — IDEMPOTENCY (CRITICAL)
    // ══════════════════════════════════════════════════════════════════════════

    public function test_process_webhook_is_idempotent_on_completed_invoice(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user);
        $invoice = $this->makeInvoice($user);

        $payload = [
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-IDEMPOTENT-1',
        ];

        // First call — processes normally
        $this->service->processWebhook($payload);

        $walletAfterFirst = Wallet::where('user_id', $user->id)->first();
        $txCountAfterFirst = Transaction::where('user_id', $user->id)->count();

        // Second call — should be a no-op
        $this->service->processWebhook($payload);

        $walletAfterSecond = Wallet::where('user_id', $user->id)->first();
        $txCountAfterSecond = Transaction::where('user_id', $user->id)->count();

        // Balance unchanged after second call
        $this->assertEquals($walletAfterFirst->balance_total, $walletAfterSecond->balance_total);
        $this->assertEquals($walletAfterFirst->balance_in_operation, $walletAfterSecond->balance_in_operation);

        // No new transactions created
        $this->assertEquals($txCountAfterFirst, $txCountAfterSecond);
    }

    public function test_process_webhook_is_idempotent_on_duplicate_tx_hash(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user);

        $invoice1 = $this->makeInvoice($user);
        $invoice2 = $this->makeInvoice($user);

        $sharedTxHash = 'TX-DUPLICATE-HASH';

        // Process first invoice
        $this->service->processWebhook([
            'invoice_id' => $invoice1->invoice_id,
            'amount'     => '100.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => $sharedTxHash,
        ]);

        $walletAfterFirst = Wallet::where('user_id', $user->id)->first();

        // Attempt with different invoice but same tx_hash — should be rejected
        $this->service->processWebhook([
            'invoice_id' => $invoice2->invoice_id,
            'amount'     => '200.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => $sharedTxHash,
        ]);

        $walletAfterSecond = Wallet::where('user_id', $user->id)->first();

        // Balance unchanged — no double deposit
        $this->assertEquals($walletAfterFirst->balance_total, $walletAfterSecond->balance_total);

        // Only one deposit transaction exists
        $depositTxCount = Transaction::where('type', 'deposit')->where('external_tx_id', $sharedTxHash)->count();
        $this->assertEquals(1, $depositTxCount);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. Financial invariants
    // ══════════════════════════════════════════════════════════════════════════

    public function test_balance_total_equals_available_plus_in_operation_after_deposit(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user   = $this->makeUser();
        $wallet = $this->makeWallet($user, '1000.00000000', '500.00000000');
        $invoice = $this->makeInvoice($user);
        $this->makeCommission(3.0);

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '250.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-INVARIANT-' . Str::random(10),
        ]);

        $wallet->refresh();

        // Invariant: balance_total == balance_available + balance_in_operation
        $expectedTotal = bcadd($wallet->balance_available, $wallet->balance_in_operation, 8);
        $this->assertEquals($expectedTotal, $wallet->balance_total);
    }

    public function test_net_amount_equals_amount_minus_fee(): void
    {
        Event::fake([DepositConfirmed::class]);

        $user = $this->makeUser();
        $this->makeWallet($user);
        $invoice = $this->makeInvoice($user);
        $this->makeCommission(4.0);

        $this->service->processWebhook([
            'invoice_id' => $invoice->invoice_id,
            'amount'     => '300.00000000',
            'currency'   => 'USDT',
            'tx_hash'    => 'TX-NET-' . Str::random(10),
        ]);

        $tx = Transaction::where('user_id', $user->id)->where('type', 'deposit')->firstOrFail();

        $expectedNet = bcsub($tx->amount, $tx->fee_amount, 8);
        $this->assertEquals($expectedNet, $tx->net_amount);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. getPendingInvoices
    // ══════════════════════════════════════════════════════════════════════════

    public function test_get_pending_invoices_returns_active_only(): void
    {
        $user = $this->makeUser();

        // Active invoice
        $this->makeInvoice($user, ['expires_at' => now()->addHours(10)]);
        // Expired invoice (still awaiting_payment but past expiry)
        $this->makeInvoice($user, ['expires_at' => now()->subMinutes(5)]);
        // Completed invoice
        $this->makeInvoice($user, ['status' => 'completed', 'expires_at' => now()->addHours(10)]);

        $pending = $this->service->getPendingInvoices($user);

        $this->assertCount(1, $pending);
        $this->assertEquals('awaiting_payment', $pending->first()->status);
    }

    public function test_get_pending_invoices_does_not_return_other_users(): void
    {
        $user1 = $this->makeUser();
        $user2 = $this->makeUser();

        $this->makeInvoice($user1);
        $this->makeInvoice($user2);

        $pending = $this->service->getPendingInvoices($user1);

        $this->assertCount(1, $pending);
        $this->assertEquals($user1->id, $pending->first()->user_id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. expireStaleInvoices
    // ══════════════════════════════════════════════════════════════════════════

    public function test_expire_stale_invoices_marks_expired(): void
    {
        $user = $this->makeUser();

        $stale = $this->makeInvoice($user, ['expires_at' => now()->subHour()]);
        $active = $this->makeInvoice($user, ['expires_at' => now()->addHours(10)]);

        $count = $this->service->expireStaleInvoices();

        $this->assertEquals(1, $count);
        $this->assertEquals('expired', $stale->refresh()->status);
        $this->assertEquals('awaiting_payment', $active->refresh()->status);
    }

    public function test_expire_stale_invoices_skips_completed(): void
    {
        $user = $this->makeUser();

        $this->makeInvoice($user, [
            'status'     => 'completed',
            'expires_at' => now()->subHour(),
        ]);

        $count = $this->service->expireStaleInvoices();

        $this->assertEquals(0, $count);
    }
}
