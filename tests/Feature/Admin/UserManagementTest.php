<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\Admin;
use App\Models\User;
use App\Models\Wallet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    // ── Acceso HTTP ──────────────────────────────────────────────────────────

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirectContains('/admin/login');
    }

    public function test_super_admin_can_access_user_list(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $this->get('/admin/users')->assertOk();
    }

    public function test_manager_can_access_user_list(): void
    {
        $this->actingAs($this->manager, 'web');

        $this->get('/admin/users')->assertOk();
    }

    public function test_super_admin_can_access_user_view(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser();

        $this->get("/admin/users/{$user->id}")->assertOk();
    }

    // ── Crear usuario ────────────────────────────────────────────────────────

    public function test_super_admin_can_create_user(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name'  => 'Nuevo Usuario',
                'email' => 'nuevo@example.com',
                'phone' => '+5491123456789',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email'  => 'nuevo@example.com',
            'name'   => 'Nuevo Usuario',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('wallets', [
            'balance_total' => 0,
        ]);
    }

    public function test_create_user_requires_name(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => '', 'email' => 'test@example.com'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_create_user_requires_email(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Test', 'email' => ''])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required']);
    }

    public function test_create_user_email_must_be_unique(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->makeUser(['email' => 'existing@example.com']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Test', 'email' => 'existing@example.com'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_create_user_email_must_be_valid_format(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Test', 'email' => 'not-an-email'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);
    }

    public function test_manager_cannot_create_user(): void
    {
        $this->actingAs($this->manager, 'web');

        Livewire::test(CreateUser::class)
            ->assertForbidden();
    }

    // ── Editar usuario ───────────────────────────────────────────────────────

    public function test_super_admin_can_edit_name_and_phone(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['name' => 'Original', 'phone' => '+1111111111']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Nombre Editado', 'phone' => '+9999999999'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'name'  => 'Nombre Editado',
            'phone' => '+9999999999',
        ]);
    }

    public function test_edit_does_not_allow_changing_email(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['email' => 'original@example.com']);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Nuevo Nombre'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'original@example.com',
        ]);
    }

    public function test_manager_cannot_edit_user(): void
    {
        $this->actingAs($this->manager, 'web');
        $user = $this->makeUser();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertForbidden();
    }

    // ── Bloquear / desbloquear ───────────────────────────────────────────────

    public function test_super_admin_can_block_active_user_from_table(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'active']);

        Livewire::test(ListUsers::class)
            ->callTableAction('block', $user, data: ['reason' => 'Conducta sospechosa en la plataforma'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('users', [
            'id'             => $user->id,
            'status'         => 'blocked',
            'blocked_reason' => 'Conducta sospechosa en la plataforma',
        ]);
        $this->assertNotNull($user->fresh()->blocked_at);
    }

    public function test_super_admin_can_unblock_user_from_table(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'blocked', 'blocked_reason' => 'Motivo previo']);

        Livewire::test(ListUsers::class)
            ->callTableAction('unblock', $user, data: ['reason' => 'Revisión completada'])
            ->assertHasNoTableActionErrors();

        $fresh = $user->fresh();
        $this->assertEquals('active', $fresh->status);
        $this->assertNull($fresh->blocked_reason);
        $this->assertNull($fresh->blocked_at);
    }

    public function test_block_action_requires_reason(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'active']);

        Livewire::test(ListUsers::class)
            ->callTableAction('block', $user, data: ['reason' => ''])
            ->assertHasErrors(['mountedTableActionsData.0.reason' => 'required']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'active']);
    }

    public function test_manager_cannot_block_user(): void
    {
        $this->actingAs($this->manager, 'web');
        $user = $this->makeUser(['status' => 'active']);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('block', $user);
    }

    public function test_block_action_is_hidden_for_already_blocked_user(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'blocked']);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('block', $user);
    }

    public function test_unblock_action_is_hidden_for_active_user(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'active']);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('unblock', $user);
    }

    public function test_super_admin_can_block_from_view_page(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'active']);

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->callAction('block', data: ['reason' => 'Motivo desde la vista de detalle'])
            ->assertHasNoActionErrors();

        $this->assertEquals('blocked', $user->fresh()->status);
    }

    // ── Casos borde ──────────────────────────────────────────────────────────

    public function test_cannot_block_pending_user_via_service(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $user = $this->makeUser(['status' => 'pending']);

        $this->expectException(\App\Exceptions\InvalidStatusTransitionException::class);

        app(\App\Services\UserService::class)->updateStatus(
            $user,
            'blocked',
            'Motivo inválido',
            $this->superAdmin,
        );
    }

    public function test_cannot_transition_active_to_pending_via_service(): void
    {
        $user = $this->makeUser(['status' => 'active']);

        $this->expectException(\App\Exceptions\InvalidStatusTransitionException::class);

        app(\App\Services\UserService::class)->updateStatus(
            $user,
            'pending',
            'Motivo',
            $this->superAdmin,
        );
    }

    public function test_view_nonexistent_user_returns_404(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->get("/admin/users/{$fakeId}")->assertNotFound();
    }

    // ── Reset password ───────────────────────────────────────────────────────

    public function test_super_admin_can_reset_user_password(): void
    {
        Notification::fake();
        $this->actingAs($this->superAdmin, 'web');
        $user            = $this->makeUser();
        $originalPassword = $user->password;

        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->callAction('resetPassword')
            ->assertHasNoActionErrors();

        $this->assertNotEquals($originalPassword, $user->fresh()->password);
        Notification::assertSentTo($user, \App\Notifications\UserCreatedByAdminNotification::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $overrides */
    private function makeUser(array $overrides = []): User
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
