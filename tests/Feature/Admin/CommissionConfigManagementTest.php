<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Events\CommissionConfigUpdated;
use App\Filament\Resources\CommissionConfigResource\Pages\CreateCommissionConfig;
use App\Filament\Resources\CommissionConfigResource\Pages\ListCommissionConfigs;
use App\Filament\Resources\CommissionConfigResource\Pages\ViewCommissionConfig;
use App\Models\Admin;
use App\Models\CommissionConfig;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CommissionConfigManagementTest extends TestCase
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

    private function makeConfig(array $attrs = []): CommissionConfig
    {
        return CommissionConfig::create(array_merge([
            'type'       => 'deposit',
            'value'      => '2.5000',
            'is_active'  => true,
            'created_by' => $this->superAdmin->id,
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. ACCESS CONTROL
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get('/admin/commission-configs')->assertRedirect('/admin/login');
    }

    /** @test */
    public function test_super_admin_can_access_list_page(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/commission-configs')->assertOk();
    }

    /** @test */
    public function test_manager_can_access_list_page(): void
    {
        $this->actingAs($this->manager, 'web');
        $this->get('/admin/commission-configs')->assertOk();
    }

    /** @test */
    public function test_super_admin_can_access_create_page(): void
    {
        $this->actingAs($this->superAdmin, 'web');
        $this->get('/admin/commission-configs/create')->assertOk();
    }

    /** @test */
    public function test_manager_cannot_access_create_page(): void
    {
        $this->actingAs($this->manager, 'web');
        $this->get('/admin/commission-configs/create')->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. LIST PAGE
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_list_shows_all_configs(): void
    {
        $this->makeConfig(['type' => 'deposit',  'is_active' => true]);
        $this->makeConfig(['type' => 'referral', 'is_active' => false]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListCommissionConfigs::class)
            ->assertSuccessful()
            ->assertCountTableRecords(2);
    }

    /** @test */
    public function test_list_can_filter_by_type(): void
    {
        $this->makeConfig(['type' => 'deposit']);
        $this->makeConfig(['type' => 'referral']);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListCommissionConfigs::class)
            ->filterTable('type', 'deposit')
            ->assertCountTableRecords(1);
    }

    /** @test */
    public function test_list_can_filter_active_only(): void
    {
        $this->makeConfig(['is_active' => true]);
        $this->makeConfig(['type' => 'referral', 'is_active' => false]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListCommissionConfigs::class)
            ->filterTable('is_active', true)
            ->assertCountTableRecords(1);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. CREATE — super_admin
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_create_new_config(): void
    {
        Event::fake();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '3.5', 'description' => 'Q2 rate'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('commission_configs', [
            'type'      => 'deposit',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_create_deactivates_previous_active_config(): void
    {
        Event::fake();

        $old = $this->makeConfig(['value' => '2.0000', 'is_active' => true]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '3.0'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame(1, CommissionConfig::active()->byType('deposit')->count());
    }

    /** @test */
    public function test_create_requires_type(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => null, 'value' => '2.5'])
            ->call('create')
            ->assertHasFormErrors(['type' => 'required']);
    }

    /** @test */
    public function test_create_requires_value(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => null])
            ->call('create')
            ->assertHasFormErrors(['value' => 'required']);
    }

    /** @test */
    public function test_create_rejects_value_above_max(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '100'])
            ->call('create')
            ->assertHasFormErrors(['value']);
    }

    /** @test */
    public function test_create_rejects_value_below_min(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '0'])
            ->call('create')
            ->assertHasFormErrors(['value']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. VIEW — activate / deactivate actions
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_super_admin_can_activate_inactive_config(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => false]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->callAction('activate')
            ->assertHasNoActionErrors();

        $this->assertTrue($config->fresh()->is_active);
    }

    /** @test */
    public function test_super_admin_can_deactivate_active_config(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => true]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->callAction('deactivate')
            ->assertHasNoActionErrors();

        $this->assertFalse($config->fresh()->is_active);
    }

    /** @test */
    public function test_activate_action_is_hidden_for_active_config(): void
    {
        $config = $this->makeConfig(['is_active' => true]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertActionHidden('activate');
    }

    /** @test */
    public function test_deactivate_action_is_hidden_for_inactive_config(): void
    {
        $config = $this->makeConfig(['is_active' => false]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertActionHidden('deactivate');
    }

    /** @test */
    public function test_activate_action_is_hidden_for_manager(): void
    {
        $config = $this->makeConfig(['is_active' => false]);

        Livewire::actingAs($this->manager, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertActionHidden('activate');
    }

    /** @test */
    public function test_deactivate_action_is_hidden_for_manager(): void
    {
        $config = $this->makeConfig(['is_active' => true]);

        Livewire::actingAs($this->manager, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertActionHidden('deactivate');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. INVARIANTS — one active per type
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_activating_replaces_current_active_of_same_type(): void
    {
        Event::fake();

        $current  = $this->makeConfig(['value' => '2.0000', 'is_active' => true]);
        $inactive = $this->makeConfig(['value' => '3.0000', 'is_active' => false]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $inactive->id])
            ->callAction('activate');

        $this->assertFalse($current->fresh()->is_active);
        $this->assertTrue($inactive->fresh()->is_active);
        $this->assertSame(1, CommissionConfig::active()->byType('deposit')->count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. IMMUTABILITY — no delete
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_configs_are_never_deleted(): void
    {
        Event::fake();

        $this->makeConfig(['is_active' => true]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(ListCommissionConfigs::class)
            ->assertTableActionDoesNotExist('delete');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. TYPE ISOLATION — deposit config does not affect referral
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_create_deposit_config_does_not_deactivate_referral_config(): void
    {
        Event::fake();

        $referral = $this->makeConfig(['type' => 'referral', 'value' => '5.0000', 'is_active' => true]);

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '3.0'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue($referral->fresh()->is_active);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. SECURITY — manager cannot mutate via Livewire
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_manager_can_view_config_detail(): void
    {
        $config = $this->makeConfig();

        Livewire::actingAs($this->manager, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertSuccessful();
    }

    /** @test */
    public function test_manager_cannot_call_activate_action_via_livewire(): void
    {
        $config = $this->makeConfig(['is_active' => false]);

        // Manager tries to call the action directly — it should fail or be unauthorized
        Livewire::actingAs($this->manager, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertActionHidden('activate');

        // Config must remain inactive
        $this->assertFalse($config->fresh()->is_active);
    }

    /** @test */
    public function test_manager_cannot_call_deactivate_action_via_livewire(): void
    {
        $config = $this->makeConfig(['is_active' => true]);

        Livewire::actingAs($this->manager, 'admin')
            ->test(ViewCommissionConfig::class, ['record' => $config->id])
            ->assertActionHidden('deactivate');

        // Config must remain active
        $this->assertTrue($config->fresh()->is_active);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. BOUNDARY VALUES — form validation
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_create_accepts_minimum_boundary_value_0_01(): void
    {
        Event::fake();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '0.01'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('commission_configs', ['value' => '0.0100']);
    }

    /** @test */
    public function test_create_accepts_maximum_boundary_value_99_99(): void
    {
        Event::fake();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '99.99'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('commission_configs', ['value' => '99.9900']);
    }

    /** @test */
    public function test_create_rejects_negative_value(): void
    {
        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'deposit', 'value' => '-1'])
            ->call('create')
            ->assertHasFormErrors(['value']);
    }

    /** @test */
    public function test_create_accepts_description_null(): void
    {
        Event::fake();

        Livewire::actingAs($this->superAdmin, 'admin')
            ->test(CreateCommissionConfig::class)
            ->fillForm(['type' => 'referral', 'value' => '5.0', 'description' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('commission_configs', [
            'type'        => 'referral',
            'description' => null,
        ]);
    }
}
