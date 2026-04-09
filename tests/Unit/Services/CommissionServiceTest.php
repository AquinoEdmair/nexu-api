<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\CommissionConfigUpdated;
use App\Models\Admin;
use App\Models\CommissionConfig;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommissionService $service;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CommissionService();

        $this->admin = Admin::create([
            'name'     => 'Admin',
            'email'    => 'admin@nexu.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeConfig(array $attrs = []): CommissionConfig
    {
        return CommissionConfig::create(array_merge([
            'type'       => 'deposit',
            'value'      => '2.5000',
            'is_active'  => true,
            'created_by' => $this->admin->id,
        ], $attrs));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. getActiveRate
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_get_active_rate_returns_active_config_value(): void
    {
        $this->makeConfig(['type' => 'deposit', 'value' => '2.5000', 'is_active' => true]);

        $rate = $this->service->getActiveRate('deposit');

        $this->assertEqualsWithDelta(2.5, $rate, 0.0001);
    }

    /** @test */
    public function test_get_active_rate_returns_zero_when_no_active_config(): void
    {
        $rate = $this->service->getActiveRate('deposit');

        $this->assertSame(0.0, $rate);
    }

    /** @test */
    public function test_get_active_rate_ignores_inactive_configs(): void
    {
        $this->makeConfig(['type' => 'deposit', 'value' => '5.0000', 'is_active' => false]);

        $rate = $this->service->getActiveRate('deposit');

        $this->assertSame(0.0, $rate);
    }

    /** @test */
    public function test_get_active_rate_is_type_scoped(): void
    {
        $this->makeConfig(['type' => 'deposit',  'value' => '2.5000', 'is_active' => true]);
        $this->makeConfig(['type' => 'referral', 'value' => '5.0000', 'is_active' => true]);

        $this->assertEqualsWithDelta(2.5, $this->service->getActiveRate('deposit'), 0.0001);
        $this->assertEqualsWithDelta(5.0, $this->service->getActiveRate('referral'), 0.0001);
    }

    /** @test */
    public function test_get_active_rate_caches_the_result(): void
    {
        Cache::flush();
        $this->makeConfig(['type' => 'deposit', 'value' => '3.0000', 'is_active' => true]);

        $this->service->getActiveRate('deposit');

        $this->assertTrue(Cache::has('commission_config:deposit'));
        $this->assertEqualsWithDelta(3.0, (float) Cache::get('commission_config:deposit'), 0.0001);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. updateConfig
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_update_config_creates_new_active_config(): void
    {
        Event::fake();

        $config = $this->service->updateConfig('deposit', 2.5, 'Tasa Q1', $this->admin);

        $this->assertSame('deposit', $config->type);
        $this->assertEqualsWithDelta(2.5, (float) $config->value, 0.0001);
        $this->assertTrue($config->is_active);
        $this->assertSame('Tasa Q1', $config->description);
        $this->assertSame($this->admin->id, $config->created_by);
    }

    /** @test */
    public function test_update_config_deactivates_previous_active(): void
    {
        Event::fake();

        $old = $this->makeConfig(['value' => '2.0000', 'is_active' => true]);

        $this->service->updateConfig('deposit', 3.0, null, $this->admin);

        $this->assertFalse($old->fresh()->is_active);
    }

    /** @test */
    public function test_update_config_only_one_active_per_type(): void
    {
        Event::fake();

        $this->service->updateConfig('deposit', 2.0, null, $this->admin);
        $this->service->updateConfig('deposit', 3.0, null, $this->admin);
        $this->service->updateConfig('deposit', 4.0, null, $this->admin);

        $activeCount = CommissionConfig::active()->byType('deposit')->count();
        $this->assertSame(1, $activeCount);
    }

    /** @test */
    public function test_update_config_does_not_affect_other_type(): void
    {
        Event::fake();

        $referral = $this->makeConfig(['type' => 'referral', 'value' => '5.0000', 'is_active' => true]);

        $this->service->updateConfig('deposit', 2.5, null, $this->admin);

        $this->assertTrue($referral->fresh()->is_active);
    }

    /** @test */
    public function test_update_config_invalidates_cache(): void
    {
        Event::fake();
        Cache::put('commission_config:deposit', 2.0, 300);

        $this->service->updateConfig('deposit', 3.0, null, $this->admin);

        $this->assertFalse(Cache::has('commission_config:deposit'));
    }

    /** @test */
    public function test_update_config_dispatches_created_event(): void
    {
        Event::fake();

        $config = $this->service->updateConfig('deposit', 2.5, null, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->config->id === $config->id
            && $e->action === 'created'
            && $e->admin->id === $this->admin->id
        );
    }

    /** @test */
    public function test_update_config_event_carries_previous_config(): void
    {
        Event::fake();

        $old = $this->makeConfig(['value' => '2.0000']);
        $this->service->updateConfig('deposit', 3.0, null, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->previousConfig?->id === $old->id
        );
    }

    /** @test */
    public function test_update_config_throws_when_value_below_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->updateConfig('deposit', 0.0, null, $this->admin);
    }

    /** @test */
    public function test_update_config_throws_when_value_exceeds_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->updateConfig('deposit', 100.0, null, $this->admin);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. activate
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_activate_sets_is_active_true(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => false]);

        $result = $this->service->activate($config, $this->admin);

        $this->assertTrue($result->is_active);
    }

    /** @test */
    public function test_activate_deactivates_current_active_of_same_type(): void
    {
        Event::fake();

        $current = $this->makeConfig(['value' => '2.0000', 'is_active' => true]);
        $target  = $this->makeConfig(['value' => '3.0000', 'is_active' => false]);

        $this->service->activate($target, $this->admin);

        $this->assertFalse($current->fresh()->is_active);
        $this->assertTrue($target->fresh()->is_active);
    }

    /** @test */
    public function test_activate_does_not_affect_other_type(): void
    {
        Event::fake();

        $referral = $this->makeConfig(['type' => 'referral', 'is_active' => true]);
        $inactive = $this->makeConfig(['type' => 'deposit', 'is_active' => false]);

        $this->service->activate($inactive, $this->admin);

        $this->assertTrue($referral->fresh()->is_active);
    }

    /** @test */
    public function test_activate_dispatches_activated_event(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => false]);

        $this->service->activate($config, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->action === 'activated' && $e->config->id === $config->id
        );
    }

    /** @test */
    public function test_activate_invalidates_cache(): void
    {
        Event::fake();
        Cache::put('commission_config:deposit', 2.0, 300);

        $config = $this->makeConfig(['is_active' => false]);
        $this->service->activate($config, $this->admin);

        $this->assertFalse(Cache::has('commission_config:deposit'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. deactivate
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_deactivate_sets_is_active_false(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => true]);

        $result = $this->service->deactivate($config, $this->admin);

        $this->assertFalse($result->is_active);
    }

    /** @test */
    public function test_deactivate_dispatches_deactivated_event(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => true]);

        $this->service->deactivate($config, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->action === 'deactivated' && $e->config->id === $config->id
        );
    }

    /** @test */
    public function test_deactivate_invalidates_cache(): void
    {
        Event::fake();
        Cache::put('commission_config:deposit', 2.5, 300);

        $config = $this->makeConfig(['is_active' => true]);
        $this->service->deactivate($config, $this->admin);

        $this->assertFalse(Cache::has('commission_config:deposit'));
    }

    /** @test */
    public function test_after_deactivate_get_active_rate_returns_zero(): void
    {
        Event::fake();
        Cache::flush();

        $config = $this->makeConfig(['value' => '3.0000', 'is_active' => true]);
        $this->service->deactivate($config, $this->admin);

        $this->assertSame(0.0, $this->service->getActiveRate('deposit'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. Snapshot isolation (historical data not affected)
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_changing_config_does_not_alter_previous_configs(): void
    {
        Event::fake();

        $first = $this->service->updateConfig('deposit', 2.0, null, $this->admin);
        $this->service->updateConfig('deposit', 4.0, null, $this->admin);

        // The first config is still in DB, unmodified except is_active=false
        $this->assertEqualsWithDelta(2.0, (float) $first->fresh()->value, 0.0001);
        $this->assertDatabaseHas('commission_configs', [
            'id'    => $first->id,
            'value' => '2.0000',
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. Boundary values
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_update_config_accepts_minimum_boundary_value(): void
    {
        Event::fake();

        $config = $this->service->updateConfig('deposit', 0.01, null, $this->admin);

        $this->assertEqualsWithDelta(0.01, (float) $config->value, 0.00001);
        $this->assertTrue($config->is_active);
    }

    /** @test */
    public function test_update_config_accepts_maximum_boundary_value(): void
    {
        Event::fake();

        $config = $this->service->updateConfig('deposit', 99.99, null, $this->admin);

        $this->assertEqualsWithDelta(99.99, (float) $config->value, 0.0001);
        $this->assertTrue($config->is_active);
    }

    /** @test */
    public function test_update_config_throws_at_exact_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateConfig('deposit', 0.0, null, $this->admin);
    }

    /** @test */
    public function test_update_config_throws_just_below_minimum(): void
    {
        // 0.009 is below the 0.01 minimum
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateConfig('deposit', 0.009, null, $this->admin);
    }

    /** @test */
    public function test_update_config_throws_at_exactly_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateConfig('deposit', 100.0, null, $this->admin);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. Cache coherence — stale cache is busted on mutation
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_get_active_rate_returns_fresh_value_after_config_update(): void
    {
        Event::fake();
        Cache::flush();

        $this->makeConfig(['value' => '2.0000', 'is_active' => true]);
        $this->service->getActiveRate('deposit'); // primes cache with 2.0

        $this->service->updateConfig('deposit', 5.0, null, $this->admin); // clears cache

        $rate = $this->service->getActiveRate('deposit'); // re-queries DB

        $this->assertEqualsWithDelta(5.0, $rate, 0.0001);
    }

    /** @test */
    public function test_get_active_rate_returns_zero_after_deactivation_and_cache_flush(): void
    {
        Event::fake();
        Cache::flush();

        $config = $this->makeConfig(['value' => '3.0000', 'is_active' => true]);
        $this->service->getActiveRate('deposit'); // primes cache

        $this->service->deactivate($config, $this->admin); // clears cache

        $rate = $this->service->getActiveRate('deposit');

        $this->assertSame(0.0, $rate);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. Idempotency
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_activate_already_active_config_is_idempotent(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => true]);

        $result = $this->service->activate($config, $this->admin);

        $this->assertTrue($result->is_active);
        $this->assertSame(1, CommissionConfig::active()->byType('deposit')->count());
    }

    /** @test */
    public function test_deactivate_already_inactive_config_is_idempotent(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => false]);

        $result = $this->service->deactivate($config, $this->admin);

        $this->assertFalse($result->is_active);
        $this->assertSame(0, CommissionConfig::active()->byType('deposit')->count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. Event payload correctness
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_update_config_event_has_null_previous_when_first_config(): void
    {
        Event::fake();

        // No previous config exists
        $this->service->updateConfig('deposit', 2.5, null, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->previousConfig === null && $e->action === 'created'
        );
    }

    /** @test */
    public function test_activate_event_has_null_previous_when_no_current_active(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => false]);

        $this->service->activate($config, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->action === 'activated' && $e->previousConfig === null
        );
    }

    /** @test */
    public function test_deactivate_event_always_has_null_previous(): void
    {
        Event::fake();

        $config = $this->makeConfig(['is_active' => true]);

        $this->service->deactivate($config, $this->admin);

        Event::assertDispatched(CommissionConfigUpdated::class, fn ($e) =>
            $e->action === 'deactivated' && $e->previousConfig === null
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. getHistory
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function test_get_history_returns_all_configs_for_type_ordered_newest_first(): void
    {
        $first  = $this->makeConfig(['value' => '1.0000', 'is_active' => false]);
        $second = $this->makeConfig(['value' => '2.0000', 'is_active' => false]);
        $third  = $this->makeConfig(['value' => '3.0000', 'is_active' => true]);

        // Force ordering via created_at (SQLite may create all in the same ms)
        $first->update(['created_at'  => now()->subMinutes(2)]);
        $second->update(['created_at' => now()->subMinutes(1)]);
        $third->update(['created_at'  => now()]);

        $history = $this->service->getHistory('deposit');

        $this->assertCount(3, $history);
        $this->assertSame($third->id, $history->first()->id);  // newest first
        $this->assertSame($first->id, $history->last()->id);   // oldest last
    }

    /** @test */
    public function test_get_history_is_scoped_to_type(): void
    {
        $this->makeConfig(['type' => 'deposit',  'value' => '2.0000']);
        $this->makeConfig(['type' => 'referral', 'value' => '5.0000']);

        $depositHistory  = $this->service->getHistory('deposit');
        $referralHistory = $this->service->getHistory('referral');

        $this->assertCount(1, $depositHistory);
        $this->assertCount(1, $referralHistory);
        $this->assertSame('deposit',  $depositHistory->first()->type);
        $this->assertSame('referral', $referralHistory->first()->type);
    }

    /** @test */
    public function test_get_history_returns_empty_collection_when_no_configs(): void
    {
        $history = $this->service->getHistory('deposit');

        $this->assertTrue($history->isEmpty());
    }
}
