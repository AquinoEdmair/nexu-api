<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\TransactionFilterDTO;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionQueryService();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        $user = User::create([
            'name'              => 'Test User',
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => 100,
            'balance_in_operation' => 500,
            'balance_total'        => 600,
        ]);

        return $user;
    }

    private function makeTx(array $attrs = []): Transaction
    {
        $user = $this->makeUser();

        return Transaction::create(array_merge([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'amount'     => '100.00000000',
            'fee_amount' => '2.00000000',
            'net_amount' => '98.00000000',
            'currency'   => 'USDT',
            'status'     => 'confirmed',
        ], $attrs));
    }

    // ── list() ───────────────────────────────────────────────────────────────

    /** @test */
    public function test_list_returns_all_without_filters(): void
    {
        $this->makeTx();
        $this->makeTx(['type' => 'yield']);

        $result = $this->service->list(new TransactionFilterDTO());

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function test_list_filters_by_type(): void
    {
        $this->makeTx(['type' => 'deposit']);
        $this->makeTx(['type' => 'yield']);

        $result = $this->service->list(new TransactionFilterDTO(types: ['deposit']));

        $this->assertCount(1, $result->items());
        $this->assertSame('deposit', $result->items()[0]->type);
    }

    /** @test */
    public function test_list_filters_by_multiple_types(): void
    {
        $this->makeTx(['type' => 'deposit']);
        $this->makeTx(['type' => 'yield']);
        $this->makeTx(['type' => 'withdrawal']);

        $result = $this->service->list(new TransactionFilterDTO(types: ['deposit', 'yield']));

        $this->assertCount(2, $result->items());
    }

    /** @test */
    public function test_list_filters_by_status(): void
    {
        $this->makeTx(['status' => 'confirmed']);
        $this->makeTx(['status' => 'pending']);

        $result = $this->service->list(new TransactionFilterDTO(statuses: ['confirmed']));

        $this->assertCount(1, $result->items());
        $this->assertSame('confirmed', $result->items()[0]->status);
    }

    /** @test */
    public function test_list_filters_by_currency(): void
    {
        $this->makeTx(['currency' => 'USDT']);
        $this->makeTx(['currency' => 'BTC']);

        $result = $this->service->list(new TransactionFilterDTO(currency: 'USDT'));

        $this->assertCount(1, $result->items());
        $this->assertSame('USDT', $result->items()[0]->currency);
    }

    /** @test */
    public function test_list_filters_by_date_from(): void
    {
        $old = $this->makeTx();
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $new = $this->makeTx();
        $new->forceFill(['created_at' => now()])->save();

        $result = $this->service->list(new TransactionFilterDTO(
            dateFrom: now()->subDay()->toDateString(),
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame($new->id, $result->items()[0]->id);
    }

    /** @test */
    public function test_list_filters_by_date_range(): void
    {
        $inRange  = $this->makeTx();
        $outRange = $this->makeTx();

        $inRange->forceFill(['created_at'  => now()->subDays(5)])->save();
        $outRange->forceFill(['created_at' => now()->subDays(20)])->save();

        $result = $this->service->list(new TransactionFilterDTO(
            dateFrom: now()->subDays(7)->toDateString(),
            dateTo:   now()->toDateString(),
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame($inRange->id, $result->items()[0]->id);
    }

    /** @test */
    public function test_list_filters_by_amount_range(): void
    {
        $this->makeTx(['net_amount' => '50.00000000']);
        $this->makeTx(['net_amount' => '500.00000000']);

        $result = $this->service->list(new TransactionFilterDTO(
            amountMin: 100.0,
            amountMax: 1000.0,
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame('500.00000000', $result->items()[0]->net_amount);
    }

    /** @test */
    public function test_list_filters_by_user_id(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        Transaction::create(['user_id' => $userA->id, 'type' => 'deposit',
            'amount' => '10', 'fee_amount' => '0', 'net_amount' => '10',
            'currency' => 'USDT', 'status' => 'confirmed']);

        Transaction::create(['user_id' => $userB->id, 'type' => 'yield',
            'amount' => '20', 'fee_amount' => '0', 'net_amount' => '20',
            'currency' => 'USDT', 'status' => 'confirmed']);

        $result = $this->service->list(new TransactionFilterDTO(userId: $userA->id));

        $this->assertCount(1, $result->items());
        $this->assertSame($userA->id, $result->items()[0]->user_id);
    }

    /** @test */
    public function test_list_searches_by_external_tx_id(): void
    {
        $this->makeTx(['external_tx_id' => 'SEARCHABLE-TX-123']);
        $this->makeTx(['external_tx_id' => 'OTHER-TX-456']);

        $result = $this->service->list(new TransactionFilterDTO(search: 'SEARCHABLE'));

        $this->assertCount(1, $result->items());
        $this->assertSame('SEARCHABLE-TX-123', $result->items()[0]->external_tx_id);
    }

    /** @test */
    public function test_list_searches_by_user_email(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        Transaction::create(['user_id' => $userA->id, 'type' => 'deposit',
            'amount' => '10', 'fee_amount' => '0', 'net_amount' => '10',
            'currency' => 'USDT', 'status' => 'confirmed']);

        Transaction::create(['user_id' => $userB->id, 'type' => 'deposit',
            'amount' => '10', 'fee_amount' => '0', 'net_amount' => '10',
            'currency' => 'USDT', 'status' => 'confirmed']);

        $result = $this->service->list(new TransactionFilterDTO(search: $userA->email));

        $this->assertCount(1, $result->items());
        $this->assertSame($userA->id, $result->items()[0]->user_id);
    }

    /** @test */
    public function test_list_eager_loads_user(): void
    {
        $this->makeTx();

        $result = $this->service->list(new TransactionFilterDTO());

        $this->assertTrue($result->items()[0]->relationLoaded('user'));
        $this->assertNotNull($result->items()[0]->user);
    }

    // ── getById() ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_get_by_id_returns_correct_transaction(): void
    {
        $tx = $this->makeTx(['type' => 'deposit', 'currency' => 'BTC']);

        $found = $this->service->getById($tx->id);

        $this->assertSame($tx->id, $found->id);
        $this->assertSame('BTC', $found->currency);
    }

    /** @test */
    public function test_get_by_id_eager_loads_user_and_wallet(): void
    {
        $tx = $this->makeTx();

        $found = $this->service->getById($tx->id);

        $this->assertTrue($found->relationLoaded('user'));
        $this->assertTrue($found->relationLoaded('wallet'));
    }

    /** @test */
    public function test_get_by_id_throws_for_nonexistent_id(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->getById('00000000-0000-0000-0000-000000000000');
    }

    // ── estimateCount() ───────────────────────────────────────────────────────

    /** @test */
    public function test_estimate_count_matches_actual_count(): void
    {
        $this->makeTx(['type' => 'deposit']);
        $this->makeTx(['type' => 'deposit']);
        $this->makeTx(['type' => 'yield']);

        $count = $this->service->estimateCount(new TransactionFilterDTO(types: ['deposit']));

        $this->assertSame(2, $count);
    }

    // ── getSummaryTotals() ────────────────────────────────────────────────────

    /** @test */
    public function test_summary_totals_aggregate_by_type(): void
    {
        $this->makeTx(['type' => 'deposit',  'net_amount' => '100.00000000']);
        $this->makeTx(['type' => 'deposit',  'net_amount' => '200.00000000']);
        $this->makeTx(['type' => 'yield',    'net_amount' => '50.00000000']);

        $summary = $this->service->getSummaryTotals(new TransactionFilterDTO());

        $this->assertSame('300', $summary->totalDeposits);
        $this->assertSame('50', $summary->totalYields);
        $this->assertSame('0', $summary->totalWithdrawals);
        $this->assertSame(3, $summary->transactionCount);
    }

    /** @test */
    public function test_summary_respects_filters(): void
    {
        $this->makeTx(['type' => 'deposit',  'net_amount' => '100.00000000', 'currency' => 'USDT']);
        $this->makeTx(['type' => 'deposit',  'net_amount' => '200.00000000', 'currency' => 'BTC']);

        $summary = $this->service->getSummaryTotals(new TransactionFilterDTO(currency: 'USDT'));

        $this->assertSame('100', $summary->totalDeposits);
        $this->assertSame(1, $summary->transactionCount);
    }
}
