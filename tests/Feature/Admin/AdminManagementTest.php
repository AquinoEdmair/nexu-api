<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AdminResource\Pages\CreateAdmin;
use App\Filament\Resources\AdminResource\Pages\EditAdmin;
use App\Filament\Resources\AdminResource\Pages\ListAdmins;
use App\Filament\Resources\AdminResource\Pages\ViewAdmin;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminManagementTest extends TestCase
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(array $attrs = []): Admin
    {
        static $count = 0;
        $count++;

        return Admin::create(array_merge([
            'name'     => "Admin {$count}",
            'email'    => "admin{$count}@nexu.com",
            'password' => Hash::make('password'),
            'role'     => 'manager',
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. Access control — ListAdmins
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_list_admins(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ListAdmins::class)
            ->assertSuccessful();
    }

    /** @test */
    public function test_manager_cannot_list_admins(): void
    {
        $this->actingAs($this->manager, 'web');

        Livewire::test(ListAdmins::class)
            ->assertForbidden();
    }

    /** @test */
    public function test_unauthenticated_user_cannot_list_admins(): void
    {
        Livewire::test(ListAdmins::class)
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. CreateAdmin
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_create_admin(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name'  => 'New Manager',
                'email' => 'new@nexu.com',
                'role'  => 'manager',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admins', [
            'email' => 'new@nexu.com',
            'role'  => 'manager',
        ]);
    }

    /** @test */
    public function test_create_admin_sets_password_automatically(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name'  => 'Auto Password Admin',
                'email' => 'autopass@nexu.com',
                'role'  => 'manager',
            ])
            ->call('create');

        $admin = Admin::where('email', 'autopass@nexu.com')->firstOrFail();
        $this->assertNotEmpty($admin->password);
    }

    /** @test */
    public function test_create_admin_requires_unique_email(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name'  => 'Duplicate',
                'email' => 'super@nexu.com', // already exists
                'role'  => 'manager',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    /** @test */
    public function test_manager_cannot_access_create_admin_page(): void
    {
        $this->actingAs($this->manager, 'web');

        Livewire::test(CreateAdmin::class)
            ->assertForbidden();
    }

    /** @test */
    public function test_create_admin_requires_name_email_and_role(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(CreateAdmin::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'email', 'role']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. ViewAdmin
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_view_admin_record(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin();

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->assertSuccessful()
            ->assertSeeText($target->name)
            ->assertSeeText($target->email);
    }

    /** @test */
    public function test_manager_cannot_view_admin_record(): void
    {
        $this->actingAs($this->manager, 'web');

        $target = $this->makeAdmin();

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. ViewAdmin — reset_2fa action
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_reset_2fa_action_is_visible_when_target_has_2fa_and_is_not_self(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin([
            'two_factor_secret'       => 'SECRET',
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->assertActionVisible('reset_2fa');
    }

    /** @test */
    public function test_reset_2fa_action_is_hidden_when_target_has_no_2fa(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin(); // no 2FA

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->assertActionHidden('reset_2fa');
    }

    /** @test */
    public function test_reset_2fa_action_is_hidden_for_self(): void
    {
        // Give the superAdmin 2FA
        $this->superAdmin->update([
            'two_factor_secret'       => 'SECRET',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ViewAdmin::class, ['record' => $this->superAdmin->id])
            ->assertActionHidden('reset_2fa');
    }

    /** @test */
    public function test_reset_2fa_action_clears_2fa_fields(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin([
            'two_factor_secret'         => 'SECRET',
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij'],
        ]);

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->callAction('reset_2fa');

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_confirmed_at);
        $this->assertNull($target->two_factor_recovery_codes);
        $this->assertFalse($target->hasTwoFactorEnabled());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. ViewAdmin — reset_password action
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_reset_password_action_is_visible_for_other_admin(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin();

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->assertActionVisible('reset_password');
    }

    /** @test */
    public function test_reset_password_action_is_hidden_for_self(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        Livewire::test(ViewAdmin::class, ['record' => $this->superAdmin->id])
            ->assertActionHidden('reset_password');
    }

    /** @test */
    public function test_reset_password_action_updates_password(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin();
        $oldPasswordHash = $target->password;

        Livewire::test(ViewAdmin::class, ['record' => $target->id])
            ->callAction('reset_password');

        $target->refresh();
        $this->assertNotEquals($oldPasswordHash, $target->password);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. EditAdmin
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_edit_name_and_role(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin(['role' => 'manager']);

        Livewire::test(EditAdmin::class, ['record' => $target->id])
            ->fillForm([
                'name' => 'Updated Name',
                'role' => 'super_admin',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();
        $this->assertEquals('Updated Name', $target->name);
        $this->assertEquals('super_admin', $target->role);
    }

    /** @test */
    public function test_edit_admin_does_not_mutate_email(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin(['email' => 'original@nexu.com']);

        // The email field is disabled + dehydrated(false), so it won't be in the save payload
        Livewire::test(EditAdmin::class, ['record' => $target->id])
            ->fillForm(['name' => 'Changed Name'])
            ->call('save');

        $target->refresh();
        $this->assertEquals('original@nexu.com', $target->email);
    }

    /** @test */
    public function test_manager_cannot_access_edit_admin_page(): void
    {
        $this->actingAs($this->manager, 'web');

        $target = $this->makeAdmin();

        Livewire::test(EditAdmin::class, ['record' => $target->id])
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. Delete is never allowed
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_admin_resource_has_no_delete_action_in_table(): void
    {
        $this->actingAs($this->superAdmin, 'web');

        $target = $this->makeAdmin();

        Livewire::test(ListAdmins::class)
            ->assertTableActionHidden('delete', $target);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. Admin Model helpers
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_has_two_factor_enabled_returns_true_when_confirmed(): void
    {
        $admin = $this->makeAdmin(['two_factor_confirmed_at' => now()]);
        $this->assertTrue($admin->hasTwoFactorEnabled());
    }

    /** @test */
    public function test_has_two_factor_enabled_returns_false_when_not_confirmed(): void
    {
        $admin = $this->makeAdmin();
        $this->assertFalse($admin->hasTwoFactorEnabled());
    }

    /** @test */
    public function test_recovery_codes_returns_empty_array_when_null(): void
    {
        $admin = $this->makeAdmin();
        $this->assertSame([], $admin->recoveryCodes());
    }

    /** @test */
    public function test_has_recovery_codes_returns_false_when_empty(): void
    {
        $admin = $this->makeAdmin();
        $this->assertFalse($admin->hasRecoveryCodes());
    }

    /** @test */
    public function test_has_recovery_codes_returns_true_when_codes_exist(): void
    {
        $admin = $this->makeAdmin([
            'two_factor_confirmed_at'   => now(),
            'two_factor_recovery_codes' => ['abcde-fghij'],
        ]);
        $this->assertTrue($admin->hasRecoveryCodes());
    }

    /** @test */
    public function test_is_super_admin_scope_filters_correctly(): void
    {
        $results = Admin::superAdmin()->pluck('email');

        $this->assertContains('super@nexu.com', $results);
        $this->assertNotContains('manager@nexu.com', $results);
    }

    /** @test */
    public function test_is_manager_scope_filters_correctly(): void
    {
        $results = Admin::manager()->pluck('email');

        $this->assertContains('manager@nexu.com', $results);
        $this->assertNotContains('super@nexu.com', $results);
    }
}
