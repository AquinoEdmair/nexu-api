<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\DTOs\TransactionFilterDTO;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransactionQueryService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'name'     => 'Super Admin',
            'email'    => 'super@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);

        $this->manager = Admin::create([
            'name'     => 'Manager',
            'email'    => 'manager@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'manager',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(): User
    {
        $user = User::create([
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'password'          => bcrypt('password'),
            'referral_code'     => strtoupper(Str::random(8)),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => 0,
            'balance_in_operation' => 0,
            'balance_total'        => 0,
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

    private function makeTxForUser(User $user, array $attrs = []): Transaction
    {
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

    // ══════════════════════════════════════════════════════════════════════════
    // 1. SEGURIDAD — Acceso
    // ══════════════════════════════════════════════════════════════════════════

    public function test_app_user_cannot_access_admin_transaction_list(): void
    {
        $user = $this->makeUser();

        // A User model authenticated via 'web' guard is not an Admin,
        // so Filament's canAccessPanel() returns false → 403 Forbidden.
        $this->actingAs($user, 'web')
            ->get('/admin/transactions')
            ->assertForbidden();
    }

    public function test_app_user_cannot_access_admin_transaction_view(): void
    {
        $tx   = $this->makeTx();
        $user = $this->makeUser();

        $this->actingAs($user, 'web')
            ->get('/admin/transactions/' . $tx->id)
            ->assertForbidden();
    }

    public function test_unauthenticated_list_redirects_to_login(): void
    {
        $this->get('/admin/transactions')->assertRedirect('/admin/login');
    }

    public function test_unauthenticated_view_redirects_to_login(): void
    {
        $tx = $this->makeTx();
        $this->get('/admin/transactions/' . $tx->id)->assertRedirect('/admin/login');
    }

    public function test_unauthenticated_create_path_redirects_to_login(): void
    {
        $this->get('/admin/transactions/create')->assertRedirect('/admin/login');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. INMUTABILIDAD — No existen rutas de mutación
    // ══════════════════════════════════════════════════════════════════════════

    public function test_create_route_does_not_exist(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions/create')->assertNotFound();
    }

    public function test_edit_route_does_not_exist(): void
    {
        $tx = $this->makeTx();
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions/' . $tx->id . '/edit')->assertNotFound();
    }

    public function test_list_table_has_no_bulk_actions(): void
    {
        $this->makeTx();
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->assertTableBulkActionDoesNotExist('delete');
    }

    public function test_transaction_record_count_is_unchanged_after_viewing(): void
    {
        $this->makeTx();
        $this->makeTx();
        $countBefore = Transaction::count();

        $this->actingAs($this->superAdmin, 'web');
        Livewire::test(ListTransactions::class)->assertSuccessful();

        $this->assertSame($countBefore, Transaction::count());
    }

    public function test_transaction_data_is_unchanged_after_viewing_detail(): void
    {
        $tx = $this->makeTx([
            'net_amount' => '123.45000000',
            'status'     => 'confirmed',
        ]);

        $original = $tx->toArray();

        $this->actingAs($this->superAdmin, 'web');
        Livewire::test(ViewTransaction::class, ['record' => $tx->id])->assertSuccessful();

        $tx->refresh();
        $this->assertSame($original['net_amount'], $tx->net_amount);
        $this->assertSame($original['status'], $tx->status);
        $this->assertSame($original['amount'], $tx->amount);
    }

    public function test_resource_cannot_create(): void
    {
        $this->assertFalse(\App\Filament\Resources\TransactionResource::canCreate());
    }

    public function test_resource_cannot_edit_any_record(): void
    {
        $tx = $this->makeTx();
        $this->assertFalse(\App\Filament\Resources\TransactionResource::canEdit($tx));
    }

    public function test_resource_cannot_delete_any_record(): void
    {
        $tx = $this->makeTx();
        $this->assertFalse(\App\Filament\Resources\TransactionResource::canDelete($tx));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. FILTROS COMBINADOS
    // ══════════════════════════════════════════════════════════════════════════

    public function test_combined_filter_type_and_status(): void
    {
        $service = new TransactionQueryService();

        $userA = $this->makeUser();
        $userB = $this->makeUser();

        // deposit + confirmed
        $target = $this->makeTxForUser($userA, ['type' => 'deposit', 'status' => 'confirmed']);
        // deposit + pending
        $this->makeTxForUser($userA, ['type' => 'deposit', 'status' => 'pending']);
        // yield + confirmed
        $this->makeTxForUser($userB, ['type' => 'yield', 'status' => 'confirmed']);

        $result = $service->list(new TransactionFilterDTO(
            types:    ['deposit'],
            statuses: ['confirmed'],
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame($target->id, $result->items()[0]->id);
    }

    public function test_combined_filter_type_and_user(): void
    {
        $service = new TransactionQueryService();

        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $target = $this->makeTxForUser($userA, ['type' => 'yield']);
        $this->makeTxForUser($userA, ['type' => 'deposit']);
        $this->makeTxForUser($userB, ['type' => 'yield']);

        $result = $service->list(new TransactionFilterDTO(
            types:  ['yield'],
            userId: $userA->id,
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame($target->id, $result->items()[0]->id);
    }

    public function test_combined_filter_date_and_type_and_user(): void
    {
        $service = new TransactionQueryService();

        $user = $this->makeUser();

        $recent  = $this->makeTxForUser($user, ['type' => 'deposit']);
        $old     = $this->makeTxForUser($user, ['type' => 'deposit']);
        $other   = $this->makeTxForUser($user, ['type' => 'yield']);

        $recent->forceFill(['created_at' => now()->subDays(1)])->save();
        $old->forceFill(['created_at'    => now()->subDays(30)])->save();
        $other->forceFill(['created_at'  => now()->subDays(1)])->save();

        $result = $service->list(new TransactionFilterDTO(
            types:    ['deposit'],
            userId:   $user->id,
            dateFrom: now()->subDays(7)->toDateString(),
            dateTo:   now()->toDateString(),
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame($recent->id, $result->items()[0]->id);
    }

    public function test_combined_filter_status_and_currency_and_amount(): void
    {
        $service = new TransactionQueryService();

        // Should match: confirmed + USDT + net_amount in [50, 200]
        $target = $this->makeTx([
            'status'     => 'confirmed',
            'currency'   => 'USDT',
            'net_amount' => '100.00000000',
        ]);

        // net_amount too low
        $this->makeTx([
            'status'     => 'confirmed',
            'currency'   => 'USDT',
            'net_amount' => '10.00000000',
        ]);

        // wrong currency
        $this->makeTx([
            'status'     => 'confirmed',
            'currency'   => 'BTC',
            'net_amount' => '100.00000000',
        ]);

        // wrong status
        $this->makeTx([
            'status'     => 'pending',
            'currency'   => 'USDT',
            'net_amount' => '100.00000000',
        ]);

        $result = $service->list(new TransactionFilterDTO(
            statuses:  ['confirmed'],
            currency:  'USDT',
            amountMin: 50.0,
            amountMax: 200.0,
        ));

        $this->assertCount(1, $result->items());
        $this->assertSame($target->id, $result->items()[0]->id);
    }

    public function test_empty_result_when_no_match_for_combined_filters(): void
    {
        $service = new TransactionQueryService();

        $this->makeTx(['type' => 'deposit', 'status' => 'confirmed', 'currency' => 'USDT']);

        $result = $service->list(new TransactionFilterDTO(
            types:    ['yield'],
            statuses: ['rejected'],
            currency: 'BTC',
        ));

        $this->assertCount(0, $result->items());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. DATOS HISTÓRICOS — Orden y exactitud
    // ══════════════════════════════════════════════════════════════════════════

    public function test_list_is_ordered_by_created_at_desc(): void
    {
        $service = new TransactionQueryService();

        $first  = $this->makeTx();
        $second = $this->makeTx();
        $third  = $this->makeTx();

        $first->forceFill(['created_at'  => now()->subDays(10)])->save();
        $second->forceFill(['created_at' => now()->subDays(5)])->save();
        $third->forceFill(['created_at'  => now()->subDays(1)])->save();

        $result = $service->list(new TransactionFilterDTO());
        $ids    = array_column($result->items(), 'id');

        $this->assertSame([$third->id, $second->id, $first->id], $ids);
    }

    public function test_decimal_amounts_are_preserved_with_full_precision(): void
    {
        $service = new TransactionQueryService();

        $tx = $this->makeTx([
            'amount'     => '99.12345678',
            'fee_amount' => '1.98024691',
            'net_amount' => '97.14320987',
        ]);

        $found = $service->getById($tx->id);

        $this->assertSame('99.12345678', $found->amount);
        $this->assertSame('1.98024691',  $found->fee_amount);
        $this->assertSame('97.14320987', $found->net_amount);
    }

    public function test_metadata_is_returned_as_array(): void
    {
        $service = new TransactionQueryService();

        $meta = ['provider' => 'nowpayments', 'invoice_id' => 'inv-001', 'tx_hash' => '0xabc'];
        $tx   = $this->makeTx(['metadata' => $meta]);

        $found = $service->getById($tx->id);

        $this->assertIsArray($found->metadata);
        $this->assertSame('nowpayments', $found->metadata['provider']);
        $this->assertSame('0xabc', $found->metadata['tx_hash']);
    }

    public function test_reference_columns_are_persisted_correctly(): void
    {
        $service  = new TransactionQueryService();
        $yieldId  = Str::uuid()->toString();

        $tx = $this->makeTx([
            'type'           => 'yield',
            'reference_type' => 'yield_log',
            'reference_id'   => $yieldId,
        ]);

        $found = $service->getById($tx->id);

        $this->assertSame('yield_log', $found->reference_type);
        $this->assertSame($yieldId, $found->reference_id);
    }

    public function test_null_external_tx_id_is_handled_gracefully(): void
    {
        $service = new TransactionQueryService();

        $tx    = $this->makeTx(['type' => 'yield', 'external_tx_id' => null]);
        $found = $service->getById($tx->id);

        $this->assertNull($found->external_tx_id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. VOLUMEN — Paginación con datos masivos
    // ══════════════════════════════════════════════════════════════════════════

    public function test_pagination_returns_correct_per_page_count(): void
    {
        $service = new TransactionQueryService();

        // Create 30 transactions (more than default page size of 25)
        $user = $this->makeUser();
        for ($i = 0; $i < 30; $i++) {
            $this->makeTxForUser($user, ['type' => 'deposit']);
        }

        $page1 = $service->list(new TransactionFilterDTO(perPage: 25));

        $this->assertSame(25, $page1->perPage());
        $this->assertSame(30, $page1->total());
        $this->assertCount(25, $page1->items());
    }

    public function test_second_page_contains_remaining_records(): void
    {
        $user = $this->makeUser();
        for ($i = 0; $i < 30; $i++) {
            $this->makeTxForUser($user);
        }

        $paginator = Transaction::query()
            ->with(['user:id,name,email,status'])
            ->latest('created_at')
            ->paginate(25, ['*'], 'page', 2);

        $this->assertCount(5, $paginator->items());
        $this->assertSame(2, $paginator->currentPage());
    }

    public function test_estimate_count_with_50_transactions(): void
    {
        $service = new TransactionQueryService();

        $user = $this->makeUser();
        for ($i = 0; $i < 50; $i++) {
            $this->makeTxForUser($user, [
                'type' => $i % 2 === 0 ? 'deposit' : 'yield',
            ]);
        }

        $allCount     = $service->estimateCount(new TransactionFilterDTO());
        $depositCount = $service->estimateCount(new TransactionFilterDTO(types: ['deposit']));
        $yieldCount   = $service->estimateCount(new TransactionFilterDTO(types: ['yield']));

        $this->assertSame(50, $allCount);
        $this->assertSame(25, $depositCount);
        $this->assertSame(25, $yieldCount);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. PERFORMANCE — Sin N+1 queries
    // ══════════════════════════════════════════════════════════════════════════

    public function test_list_does_not_cause_n_plus_1_queries(): void
    {
        $service = new TransactionQueryService();

        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $userC = $this->makeUser();

        $this->makeTxForUser($userA);
        $this->makeTxForUser($userB);
        $this->makeTxForUser($userC);

        // Warm up (first query runs migrations-related checks in some drivers)
        $service->list(new TransactionFilterDTO(perPage: 3));

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $result = $service->list(new TransactionFilterDTO(perPage: 25));

        // Laravel eager loading generates 3 queries: SELECT transactions, SELECT users IN(...), COUNT(*)
        // N+1 would be 1 + N (one extra per record). Asserting ≤3 proves no N+1.
        $this->assertLessThanOrEqual(3, $queryCount,
            "Expected ≤3 queries (data + eager users + count), got {$queryCount}. Possible N+1."
        );
        $this->assertCount(3, $result->items());
        $this->assertTrue($result->items()[0]->relationLoaded('user'));
    }

    public function test_view_detail_does_not_cause_n_plus_1_queries(): void
    {
        $service = new TransactionQueryService();

        $tx = $this->makeTx(['metadata' => ['key' => 'value']]);

        // Warm up
        $service->getById($tx->id);

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $found = $service->getById($tx->id);

        // 2 queries: SELECT transaction, SELECT user IN(...). Wallet may add a 3rd if present.
        // N+1 would be 1+N per relation. Asserting ≤2 proves no N+1.
        $this->assertLessThanOrEqual(2, $queryCount,
            "Expected ≤2 queries for getById(), got {$queryCount}."
        );
        $this->assertTrue($found->relationLoaded('user'));
        $this->assertTrue($found->relationLoaded('wallet'));
    }

    public function test_list_query_count_does_not_grow_with_more_records(): void
    {
        $service = new TransactionQueryService();

        // Create 10 transactions (different users = worst case for N+1)
        for ($i = 0; $i < 10; $i++) {
            $this->makeTx();
        }

        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        $service->list(new TransactionFilterDTO(perPage: 10));

        $queriesFor10 = $queryCount;

        // Create 10 more
        for ($i = 0; $i < 10; $i++) {
            $this->makeTx();
        }

        $queryCount = 0;
        $service->list(new TransactionFilterDTO(perPage: 20));
        $queriesFor20 = $queryCount;

        // Query count must stay constant regardless of record count
        $this->assertSame($queriesFor10, $queriesFor20,
            "Query count grew from {$queriesFor10} to {$queriesFor20} when doubling records — N+1 detected."
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. INTEGRIDAD DEL LEDGER — Las transacciones no se pueden crear desde el panel
    // ══════════════════════════════════════════════════════════════════════════

    public function test_transaction_count_is_unchanged_after_full_list_interaction(): void
    {
        $tx1 = $this->makeTx(['type' => 'deposit']);
        $tx2 = $this->makeTx(['type' => 'yield']);

        $countBefore = Transaction::count();

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->assertSuccessful()
            ->filterTable('type', ['deposit'])
            ->assertCanSeeTableRecords([$tx1])
            ->assertCanNotSeeTableRecords([$tx2]);

        $this->assertSame($countBefore, Transaction::count());
    }

    public function test_transactions_survive_admin_session_intact(): void
    {
        $txBefore = $this->makeTx([
            'type'       => 'deposit',
            'net_amount' => '555.12345678',
            'currency'   => 'ETH',
            'status'     => 'confirmed',
        ]);

        // Simulate full admin session: login, list, view
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions')->assertOk();
        $this->get('/admin/transactions/' . $txBefore->id)->assertOk();

        $txAfter = Transaction::find($txBefore->id);

        $this->assertSame($txBefore->id,         $txAfter->id);
        $this->assertSame($txBefore->net_amount,  $txAfter->net_amount);
        $this->assertSame($txBefore->currency,    $txAfter->currency);
        $this->assertSame($txBefore->status,      $txAfter->status);
        $this->assertSame($txBefore->type,        $txAfter->type);
        $this->assertSame(
            $txBefore->created_at->toISOString(),
            $txAfter->created_at->toISOString()
        );
    }
}
