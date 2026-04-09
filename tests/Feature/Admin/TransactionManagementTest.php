<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Models\Admin;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionManagementTest extends TestCase
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
        $user = User::factory()->create();
        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => 0,
            'balance_in_operation' => 0,
            'balance_total'        => 0,
        ]);
        return $user;
    }

    private function makeTransaction(array $attrs = []): Transaction
    {
        $user = $this->makeUser();

        return Transaction::create(array_merge([
            'user_id'        => $user->id,
            'type'           => 'deposit',
            'amount'         => '100.00000000',
            'fee_amount'     => '2.00000000',
            'net_amount'     => '98.00000000',
            'currency'       => 'USDT',
            'status'         => 'confirmed',
            'external_tx_id' => 'txid-' . uniqid(),
        ], $attrs));
    }

    // ── HTTP access ──────────────────────────────────────────────────────────

    /** @test */
    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get('/admin/transactions')->assertRedirect('/admin/login');
    }

    /** @test */
    public function test_super_admin_can_access_transaction_list(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions')->assertOk();
    }

    /** @test */
    public function test_manager_can_access_transaction_list(): void
    {
        $this->actingAs($this->manager, 'web');
        $this->get('/admin/transactions')->assertOk();
    }

    /** @test */
    public function test_super_admin_can_access_transaction_view(): void
    {
        $tx = $this->makeTransaction();
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions/' . $tx->id)->assertOk();
    }

    // ── Table renders ────────────────────────────────────────────────────────

    /** @test */
    public function test_list_renders_transactions(): void
    {
        $this->makeTransaction();
        $this->makeTransaction(['type' => 'yield', 'status' => 'confirmed']);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Transaction::all());
    }

    /** @test */
    public function test_list_shows_user_name_and_email(): void
    {
        $user = $this->makeUser();
        Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'deposit',
            'amount'     => '100.00000000',
            'fee_amount' => '0.00000000',
            'net_amount' => '100.00000000',
            'currency'   => 'USDT',
            'status'     => 'confirmed',
        ]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->assertSuccessful()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    // ── Filters ──────────────────────────────────────────────────────────────

    /** @test */
    public function test_filter_by_type_shows_only_matching(): void
    {
        $deposit = $this->makeTransaction(['type' => 'deposit']);
        $yield   = $this->makeTransaction(['type' => 'yield', 'external_tx_id' => null]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->filterTable('type', ['deposit'])
            ->assertCanSeeTableRecords([$deposit])
            ->assertCanNotSeeTableRecords([$yield]);
    }

    /** @test */
    public function test_filter_by_status_shows_only_matching(): void
    {
        $confirmed = $this->makeTransaction(['status' => 'confirmed']);
        $pending   = $this->makeTransaction(['type' => 'withdrawal', 'status' => 'pending', 'external_tx_id' => null]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->filterTable('status', ['confirmed'])
            ->assertCanSeeTableRecords([$confirmed])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    /** @test */
    public function test_filter_by_currency_shows_only_matching(): void
    {
        $usdt = $this->makeTransaction(['currency' => 'USDT']);
        $btc  = $this->makeTransaction(['currency' => 'BTC', 'external_tx_id' => null]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->filterTable('currency', 'USDT')
            ->assertCanSeeTableRecords([$usdt])
            ->assertCanNotSeeTableRecords([$btc]);
    }

    // ── View page ────────────────────────────────────────────────────────────

    /** @test */
    public function test_view_shows_transaction_details(): void
    {
        $tx = $this->makeTransaction([
            'type'           => 'deposit',
            'amount'         => '200.00000000',
            'net_amount'     => '196.00000000',
            'currency'       => 'USDT',
            'status'         => 'confirmed',
            'external_tx_id' => 'unique-tx-abc123',
        ]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ViewTransaction::class, ['record' => $tx->id])
            ->assertSuccessful()
            ->assertSee('unique-tx-abc123');
    }

    /** @test */
    public function test_view_nonexistent_transaction_returns_404(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    /** @test */
    public function test_view_shows_user_email(): void
    {
        $user = $this->makeUser();
        $tx   = Transaction::create([
            'user_id'    => $user->id,
            'type'       => 'yield',
            'amount'     => '50.00000000',
            'fee_amount' => '0.00000000',
            'net_amount' => '50.00000000',
            'currency'   => 'USDT',
            'status'     => 'confirmed',
        ]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ViewTransaction::class, ['record' => $tx->id])
            ->assertSuccessful()
            ->assertSee($user->email);
    }

    // ── Immutability — no edit / delete actions ───────────────────────────────

    /** @test */
    public function test_list_has_no_edit_action(): void
    {
        $tx = $this->makeTransaction();

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListTransactions::class)
            ->assertTableActionDoesNotExist('edit', record: $tx)
            ->assertTableActionDoesNotExist('delete', record: $tx);
    }

    /** @test */
    public function test_view_has_no_edit_or_delete_header_actions(): void
    {
        $tx = $this->makeTransaction();

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ViewTransaction::class, ['record' => $tx->id])
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete');
    }

    /** @test */
    public function test_no_create_route_exists(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/transactions/create')->assertNotFound();
    }

    // ── Policy ───────────────────────────────────────────────────────────────

    /** @test */
    public function test_transaction_policy_allows_view_for_manager(): void
    {
        $tx = $this->makeTransaction();

        $this->actingAs($this->manager, 'web');
        $this->get('/admin/transactions/' . $tx->id)->assertOk();
    }

    /** @test */
    public function test_transaction_policy_blocks_create(): void
    {
        $policy = new \App\Policies\TransactionPolicy();
        $this->assertFalse($policy->create($this->superAdmin));
    }

    /** @test */
    public function test_transaction_policy_blocks_update(): void
    {
        $tx     = $this->makeTransaction();
        $policy = new \App\Policies\TransactionPolicy();
        $this->assertFalse($policy->update($this->superAdmin, $tx));
    }

    /** @test */
    public function test_transaction_policy_blocks_delete(): void
    {
        $tx     = $this->makeTransaction();
        $policy = new \App\Policies\TransactionPolicy();
        $this->assertFalse($policy->delete($this->superAdmin, $tx));
    }

    /** @test */
    public function test_transaction_policy_allows_view_for_both_roles(): void
    {
        $tx     = $this->makeTransaction();
        $policy = new \App\Policies\TransactionPolicy();

        $this->assertTrue($policy->view($this->superAdmin, $tx));
        $this->assertTrue($policy->view($this->manager, $tx));
        $this->assertTrue($policy->viewAny($this->superAdmin));
        $this->assertTrue($policy->viewAny($this->manager));
    }
}
