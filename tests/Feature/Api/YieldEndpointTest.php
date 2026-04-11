<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\YieldLog;
use App\Models\YieldLogUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class YieldEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_his_yield_history(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Create yield logs
        $log1 = YieldLog::factory()->create(['description' => 'May Yield']);
        $log2 = YieldLog::factory()->create(['description' => 'June Yield']);

        // Assign yields to current user
        YieldLogUser::create([
            'yield_log_id' => $log1->id,
            'user_id' => $user->id,
            'balance_before' => 1000,
            'balance_after' => 1050,
            'amount_applied' => 50,
            'status' => 'applied'
        ]);

        // Assign yield to another user
        YieldLogUser::create([
            'yield_log_id' => $log2->id,
            'user_id' => $otherUser->id,
            'balance_before' => 2000,
            'balance_after' => 2100,
            'amount_applied' => 100,
            'status' => 'applied'
        ]);

        Sanctum::actingAs($user, ['*'], 'api');

        $response = $this->getJson('/api/v1/yields');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount_applied', '50.00000000')
            ->assertJsonPath('data.0.yield_log.description', 'May Yield');
    }

    public function test_unauthenticated_user_cannot_view_yields(): void
    {
        $this->getJson('/api/v1/yields')->assertUnauthorized();
    }
}
