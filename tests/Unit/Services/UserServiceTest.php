<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\UserCreatedByAdmin;
use App\Events\UserStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\CommissionConfig;
use App\Models\User;
use App\Models\Wallet;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);

        $this->admin = Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);
    }

    // ── createUser ───────────────────────────────────────────────────────────

    public function test_create_user_creates_user_and_wallet(): void
    {
        Event::fake();

        $user = $this->service->createUser([
            'name'  => 'Juan Pérez',
            'email' => 'juan@example.com',
            'phone' => '+5491112345678',
        ], $this->admin);

        $this->assertDatabaseHas('users', [
            'email'  => 'juan@example.com',
            'name'   => 'Juan Pérez',
            'status' => 'active',
        ]);

        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('wallets', [
            'user_id'              => $user->id,
            'balance_available'    => 0,
            'balance_in_operation' => 0,
            'balance_total'        => 0,
        ]);
    }

    public function test_create_user_generates_unique_referral_code(): void
    {
        Event::fake();

        $user = $this->service->createUser([
            'name'  => 'María García',
            'email' => 'maria@example.com',
        ], $this->admin);

        $this->assertNotEmpty($user->referral_code);
        $this->assertEquals(8, strlen($user->referral_code));
    }

    public function test_create_user_with_valid_referral_code_creates_referral_record(): void
    {
        Event::fake();

        $referrer = User::create([
            'name'          => 'Referrer',
            'email'         => 'referrer@example.com',
            'password'      => Hash::make('pass'),
            'referral_code' => 'REFER001',
            'status'        => 'active',
        ]);

        $user = $this->service->createUser([
            'name'             => 'Referred',
            'email'            => 'referred@example.com',
            'referred_by_code' => 'REFER001',
        ], $this->admin);

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referred_id' => $user->id,
        ]);

        $this->assertEquals($referrer->id, $user->referred_by);
    }

    public function test_create_user_with_invalid_referral_code_ignores_it(): void
    {
        Event::fake();

        $user = $this->service->createUser([
            'name'             => 'Usuario',
            'email'            => 'usuario@example.com',
            'referred_by_code' => 'INVALID1',
        ], $this->admin);

        $this->assertNull($user->referred_by);
        $this->assertDatabaseEmpty('referrals');
    }

    public function test_create_user_dispatches_event(): void
    {
        Event::fake();

        $this->service->createUser([
            'name'  => 'Test',
            'email' => 'test@example.com',
        ], $this->admin);

        Event::assertDispatched(UserCreatedByAdmin::class);
    }

    // ── updateProfile ────────────────────────────────────────────────────────

    public function test_update_profile_changes_name_and_phone(): void
    {
        Event::fake();

        $user = $this->createUser();

        $this->service->updateProfile($user, [
            'name'  => 'Nuevo Nombre',
            'phone' => '+5491199999999',
        ], $this->admin);

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'name'  => 'Nuevo Nombre',
            'phone' => '+5491199999999',
        ]);
    }

    public function test_update_profile_does_not_change_email(): void
    {
        Event::fake();

        $user = $this->createUser(['email' => 'original@example.com']);

        $this->service->updateProfile($user, [
            'name'  => 'Nuevo Nombre',
            'email' => 'hacked@example.com',
        ], $this->admin);

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'original@example.com',
        ]);
    }

    // ── updateStatus ─────────────────────────────────────────────────────────

    public function test_block_active_user(): void
    {
        Event::fake();

        $user = $this->createUser(['status' => 'active']);

        $this->service->updateStatus($user, 'blocked', 'Conducta sospechosa', $this->admin);

        $this->assertDatabaseHas('users', [
            'id'             => $user->id,
            'status'         => 'blocked',
            'blocked_reason' => 'Conducta sospechosa',
        ]);

        $this->assertNotNull($user->fresh()->blocked_at);
    }

    public function test_unblock_blocked_user(): void
    {
        Event::fake();

        $user = $this->createUser(['status' => 'blocked', 'blocked_reason' => 'Motivo previo']);

        $this->service->updateStatus($user, 'active', 'Revisión completada', $this->admin);

        $fresh = $user->fresh();
        $this->assertEquals('active', $fresh->status);
        $this->assertNull($fresh->blocked_reason);
        $this->assertNull($fresh->blocked_at);
    }

    public function test_block_pending_user_throws_exception(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $user = $this->createUser(['status' => 'pending']);

        $this->service->updateStatus($user, 'blocked', 'Motivo', $this->admin);
    }

    public function test_active_to_pending_throws_exception(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        $user = $this->createUser(['status' => 'active']);

        $this->service->updateStatus($user, 'pending', 'Motivo', $this->admin);
    }

    public function test_update_status_dispatches_event(): void
    {
        Event::fake();

        $user = $this->createUser(['status' => 'active']);

        $this->service->updateStatus($user, 'blocked', 'Test', $this->admin);

        Event::assertDispatched(UserStatusChanged::class, function (UserStatusChanged $event) use ($user): bool {
            return $event->user->id === $user->id
                && $event->oldStatus === 'active'
                && $event->newStatus === 'blocked';
        });
    }

    // ── resetPassword ────────────────────────────────────────────────────────

    public function test_reset_password_changes_password(): void
    {
        Notification::fake();

        $user            = $this->createUser();
        $originalHash    = $user->password;

        $this->service->resetPassword($user, $this->admin);

        $this->assertNotEquals($originalHash, $user->fresh()->password);
    }

    public function test_reset_password_sends_notification(): void
    {
        Notification::fake();

        $user = $this->createUser();

        $this->service->resetPassword($user, $this->admin);

        Notification::assertSentTo(
            $user,
            \App\Notifications\UserCreatedByAdminNotification::class,
        );
    }

    // ── getProfile ───────────────────────────────────────────────────────────

    public function test_get_profile_returns_dto_with_wallet(): void
    {
        Event::fake();

        $user = $this->service->createUser([
            'name'  => 'Profile Test',
            'email' => 'profile@example.com',
        ], $this->admin);

        $dto = $this->service->getProfile($user->id);

        $this->assertEquals($user->id, $dto->user->id);
        $this->assertNotNull($dto->wallet);
        $this->assertEquals('Bronce', $dto->eliteLevel);
        $this->assertEquals('0', $dto->totalElitePoints);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @param  array<string, mixed> $overrides */
    private function createUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name'          => 'Test User',
            'email'         => 'user' . uniqid() . '@example.com',
            'password'      => Hash::make('password'),
            'referral_code' => strtoupper(substr(uniqid(), 0, 8)),
            'status'        => 'active',
        ], $overrides));

        Wallet::create([
            'user_id'              => $user->id,
            'balance_available'    => 0,
            'balance_in_operation' => 0,
            'balance_total'        => 0,
        ]);

        return $user;
    }
}
