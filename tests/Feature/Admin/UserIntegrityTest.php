<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Models\Wallet;
use App\Policies\UserPolicy;
use App\Services\UserService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $superAdmin;
    private Admin $manager;
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);

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

    // ── No eliminación ───────────────────────────────────────────────────────

    public function test_delete_policy_is_always_false_for_super_admin(): void
    {
        $policy = new UserPolicy();
        $user   = $this->makeUser();

        $this->assertFalse($policy->delete($this->superAdmin, $user));
    }

    public function test_delete_policy_is_always_false_for_manager(): void
    {
        $policy = new UserPolicy();
        $user   = $this->makeUser();

        $this->assertFalse($policy->delete($this->manager, $user));
    }

    public function test_blocking_user_does_not_delete_the_record(): void
    {
        Event::fake();
        $user = $this->makeUser(['status' => 'active']);

        $this->service->updateStatus($user, 'blocked', 'Motivo de prueba', $this->superAdmin);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseCount('users', 1);
    }

    // ── Wallets intactos ─────────────────────────────────────────────────────

    public function test_blocking_user_does_not_modify_wallet_balances(): void
    {
        Event::fake();

        $user = $this->makeUser(['status' => 'active'], [
            'balance_available'    => '100.50000000',
            'balance_in_operation' => '500.00000000',
            'balance_total'        => '600.50000000',
        ]);

        $this->service->updateStatus($user, 'blocked', 'Motivo', $this->superAdmin);

        $wallet = $user->fresh()->wallet;

        $this->assertEquals('100.50000000', $wallet->balance_available);
        $this->assertEquals('500.00000000', $wallet->balance_in_operation);
        $this->assertEquals('600.50000000', $wallet->balance_total);
    }

    public function test_editing_user_profile_does_not_modify_wallet_balances(): void
    {
        $user = $this->makeUser(walletOverrides: [
            'balance_available'    => '250.00000000',
            'balance_in_operation' => '750.00000000',
            'balance_total'        => '1000.00000000',
        ]);

        $this->service->updateProfile($user, ['name' => 'Nuevo Nombre'], $this->superAdmin);

        $wallet = $user->fresh()->wallet;

        $this->assertEquals('250.00000000', $wallet->balance_available);
        $this->assertEquals('750.00000000', $wallet->balance_in_operation);
        $this->assertEquals('1000.00000000', $wallet->balance_total);
    }

    public function test_resetting_password_does_not_modify_wallet(): void
    {
        Notification::fake();

        $user = $this->makeUser(walletOverrides: [
            'balance_available' => '50.00000000',
            'balance_total'     => '50.00000000',
        ]);

        $this->service->resetPassword($user, $this->superAdmin);

        $this->assertEquals('50.00000000', $user->fresh()->wallet->balance_available);
    }

    // ── Sin transacciones ────────────────────────────────────────────────────

    public function test_creating_user_does_not_create_transactions(): void
    {
        Event::fake();
        Notification::fake();

        $this->service->createUser([
            'name'  => 'Usuario Test',
            'email' => 'txtest@example.com',
        ], $this->superAdmin);

        $this->assertDatabaseEmpty('transactions');
    }

    public function test_editing_user_profile_does_not_create_transactions(): void
    {
        $user = $this->makeUser();

        $this->service->updateProfile($user, ['name' => 'Cambiado'], $this->superAdmin);

        $this->assertDatabaseEmpty('transactions');
    }

    public function test_blocking_user_does_not_create_transactions(): void
    {
        Event::fake();
        $user = $this->makeUser(['status' => 'active']);

        $this->service->updateStatus($user, 'blocked', 'Motivo', $this->superAdmin);

        $this->assertDatabaseEmpty('transactions');
    }

    public function test_unblocking_user_does_not_create_transactions(): void
    {
        Event::fake();
        $user = $this->makeUser(['status' => 'blocked']);

        $this->service->updateStatus($user, 'active', 'Revisión OK', $this->superAdmin);

        $this->assertDatabaseEmpty('transactions');
    }

    public function test_resetting_password_does_not_create_transactions(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->service->resetPassword($user, $this->superAdmin);

        $this->assertDatabaseEmpty('transactions');
    }

    // ── Auditoría ────────────────────────────────────────────────────────────

    public function test_blocking_user_creates_activity_log_entry(): void
    {
        Event::fake();
        $user = $this->makeUser(['status' => 'active']);

        $this->service->updateStatus($user, 'blocked', 'Motivo auditado', $this->superAdmin);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'causer_type'  => Admin::class,
            'causer_id'    => $this->superAdmin->id,
        ]);
    }

    public function test_creating_user_creates_activity_log_entry(): void
    {
        Event::fake();
        Notification::fake();

        $user = $this->service->createUser([
            'name'  => 'Auditado',
            'email' => 'audit@example.com',
        ], $this->superAdmin);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'causer_type'  => Admin::class,
            'causer_id'    => $this->superAdmin->id,
        ]);
    }

    public function test_editing_profile_creates_activity_log_entry(): void
    {
        $user = $this->makeUser();

        $this->service->updateProfile($user, ['name' => 'Nombre nuevo'], $this->superAdmin);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'causer_id'    => $this->superAdmin->id,
        ]);
    }

    // ── Invariante de wallet ─────────────────────────────────────────────────

    public function test_creating_user_always_creates_an_associated_wallet(): void
    {
        Event::fake();
        Notification::fake();

        $user = $this->service->createUser([
            'name'  => 'Con Wallet',
            'email' => 'wallet@example.com',
        ], $this->superAdmin);

        $this->assertNotNull($user->fresh()->wallet);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id]);
    }

    public function test_wallet_balance_invariant_holds_after_create(): void
    {
        Event::fake();
        Notification::fake();

        $user   = $this->service->createUser([
            'name'  => 'Invariant',
            'email' => 'invariant@example.com',
        ], $this->superAdmin);

        $wallet = $user->fresh()->wallet;

        $this->assertEquals(
            (float) $wallet->balance_total,
            (float) $wallet->balance_available + (float) $wallet->balance_in_operation,
        );
    }

    // ── Autorización (policy) ────────────────────────────────────────────────

    public function test_super_admin_can_create_users(): void
    {
        $this->assertTrue((new UserPolicy())->create($this->superAdmin));
    }

    public function test_manager_cannot_create_users(): void
    {
        $this->assertFalse((new UserPolicy())->create($this->manager));
    }

    public function test_super_admin_can_update_users(): void
    {
        $user = $this->makeUser();
        $this->assertTrue((new UserPolicy())->update($this->superAdmin, $user));
    }

    public function test_manager_cannot_update_users(): void
    {
        $user = $this->makeUser();
        $this->assertFalse((new UserPolicy())->update($this->manager, $user));
    }

    public function test_both_roles_can_view_users(): void
    {
        $user   = $this->makeUser();
        $policy = new UserPolicy();

        $this->assertTrue($policy->view($this->superAdmin, $user));
        $this->assertTrue($policy->view($this->manager, $user));
    }

    public function test_both_roles_can_list_users(): void
    {
        $policy = new UserPolicy();

        $this->assertTrue($policy->viewAny($this->superAdmin));
        $this->assertTrue($policy->viewAny($this->manager));
    }

    public function test_block_policy_is_true_only_for_super_admin_and_active_user(): void
    {
        $policy      = new UserPolicy();
        $activeUser  = $this->makeUser(['status' => 'active']);
        $blockedUser = $this->makeUser(['status' => 'blocked']);

        $this->assertTrue($policy->block($this->superAdmin, $activeUser));
        $this->assertFalse($policy->block($this->superAdmin, $blockedUser));
        $this->assertFalse($policy->block($this->manager, $activeUser));
    }

    public function test_unblock_policy_is_true_only_for_super_admin_and_blocked_user(): void
    {
        $policy      = new UserPolicy();
        $activeUser  = $this->makeUser(['status' => 'active']);
        $blockedUser = $this->makeUser(['status' => 'blocked']);

        $this->assertTrue($policy->unblock($this->superAdmin, $blockedUser));
        $this->assertFalse($policy->unblock($this->superAdmin, $activeUser));
        $this->assertFalse($policy->unblock($this->manager, $blockedUser));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $userOverrides
     * @param  array<string, mixed> $walletOverrides
     */
    private function makeUser(
        array $userOverrides   = [],
        array $walletOverrides = [],
    ): User {
        $user = User::create(array_merge([
            'name'          => 'Test User',
            'email'         => 'user' . uniqid() . '@example.com',
            'password'      => Hash::make('password'),
            'referral_code' => strtoupper(\Illuminate\Support\Str::random(8)),
            'status'        => 'active',
        ], $userOverrides));

        Wallet::create(array_merge([
            'user_id'              => $user->id,
            'balance_available'    => 0,
            'balance_in_operation' => 0,
            'balance_total'        => 0,
        ], $walletOverrides, ['user_id' => $user->id]));

        return $user;
    }
}
