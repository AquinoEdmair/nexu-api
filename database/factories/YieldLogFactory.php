<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\YieldLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\YieldLog>
 */
final class YieldLogFactory extends Factory
{
    protected $model = YieldLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applied_by' => Admin::factory(),
            'type' => 'percentage',
            'value' => 2.5,
            'scope' => 'all',
            'negative_policy' => 'skip',
            'status' => 'completed',
            'description' => $this->faker->sentence(),
            'applied_at' => now(),
            'completed_at' => now(),
        ];
    }
}
