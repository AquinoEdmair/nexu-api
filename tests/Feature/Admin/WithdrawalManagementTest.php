<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\WithdrawalRequestResource\Pages\ListWithdrawalRequests;
use App\Filament\Resources\WithdrawalRequestResource\Pages\ViewWithdrawalRequest;
use App\Models\Admin;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WithdrawalManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
            'balance_available'    => 500.0,
            'balance_in_operation' => 1000.0,
            'balance_total'        => 1500.0,
        ]);

        return $user;
    }

    private function makeRequest(User $user, string $status = 'pending', float $amount = 100.0): WithdrawalRequest
    {
        return WithdrawalRequest::create([
            'user_id'             => $user->id,
            'amount'              => $amount,
            'currency'            => 'USDT',
            'destination_address' => 'TXabc123',
            'status'              => $status,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. LIST PAGE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_list_withdrawal_requests(): void
    {
        $user = $this->makeUser();
        $this->makeRequest($user, 'pending');
        $this->makeRequest($user, 'completed');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ListWithdrawalRequests::class)
            ->assertSuccessful()
            ->assertCountTableRecords(2);
    }

    /** @test */
    public function test_list_can_filter_by_status(): void
    {
        $user = $this->makeUser();
        $this->makeRequest($user, 'pending');
        $this->makeRequest($user, 'completed');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ListWithdrawalRequests::class)
            ->filterTable('status', 'pending')
            ->assertCountTableRecords(1);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. VIEW PAGE — APPROVE ACTION
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_approve_a_pending_request(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'pending');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('approve')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('withdrawal_requests', [
            'id'          => $request->id,
            'status'      => 'approved',
            'reviewed_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function test_approve_action_is_hidden_for_non_pending_requests(): void
    {
        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'approved');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->assertActionHidden('approve');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. VIEW PAGE — COMPLETE ACTION
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_complete_an_approved_request(): void
    {
        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'approved');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('complete', data: ['tx_hash' => '0xabc123def456'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('withdrawal_requests', [
            'id'      => $request->id,
            'status'  => 'completed',
            'tx_hash' => '0xabc123def456',
        ]);
    }

    /** @test */
    public function test_complete_action_requires_tx_hash(): void
    {
        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'approved');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('complete', data: ['tx_hash' => ''])
            ->assertHasActionErrors(['tx_hash' => 'required']);
    }

    /** @test */
    public function test_complete_action_is_hidden_for_pending_requests(): void
    {
        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'pending');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->assertActionHidden('complete');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. VIEW PAGE — REJECT ACTION
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_can_reject_a_pending_request(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'pending');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('reject', data: ['rejection_reason' => 'Dirección inválida'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('withdrawal_requests', [
            'id'               => $request->id,
            'status'           => 'rejected',
            'rejection_reason' => 'Dirección inválida',
        ]);
    }

    /** @test */
    public function test_admin_can_reject_an_approved_request(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'approved');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('reject', data: ['rejection_reason' => 'Problema en la red'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('withdrawal_requests', [
            'id'     => $request->id,
            'status' => 'rejected',
        ]);
    }

    /** @test */
    public function test_reject_action_requires_rejection_reason(): void
    {
        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'pending');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('reject', data: ['rejection_reason' => ''])
            ->assertHasActionErrors(['rejection_reason' => 'required']);
    }

    /** @test */
    public function test_reject_action_is_hidden_for_completed_requests(): void
    {
        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'completed');

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->assertActionHidden('reject');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. BALANCE INTEGRITY
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_reject_releases_funds_back_to_user_wallet(): void
    {
        Event::fake();

        $user    = $this->makeUser();
        $request = $this->makeRequest($user, 'pending', 100.0);

        // Simulate that funds were already reserved (as done by WithdrawalService::create)
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();
        $wallet->update([
            'balance_available' => 400.0,
            'balance_total'     => 1400.0,
        ]);

        Livewire::actingAs($this->admin, 'admin')
            ->test(ViewWithdrawalRequest::class, ['record' => $request->id])
            ->callAction('reject', data: ['rejection_reason' => 'Fondos bloqueados']);

        $wallet->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $wallet->balance_available, 0.000001);
        $this->assertEqualsWithDelta(1500.0, (float) $wallet->balance_total, 0.000001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. NAVIGATION BADGE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_navigation_badge_shows_pending_count(): void
    {
        $user = $this->makeUser();
        $this->makeRequest($user, 'pending');
        $this->makeRequest($user, 'pending');
        $this->makeRequest($user, 'completed');

        $badge = \App\Filament\Resources\WithdrawalRequestResource::getNavigationBadge();

        $this->assertSame('2', $badge);
    }

    /** @test */
    public function test_navigation_badge_is_null_when_no_pending(): void
    {
        $user = $this->makeUser();
        $this->makeRequest($user, 'completed');

        $badge = \App\Filament\Resources\WithdrawalRequestResource::getNavigationBadge();

        $this->assertNull($badge);
    }
}
