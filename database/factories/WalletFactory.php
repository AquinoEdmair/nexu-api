<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $inOperation = (float) $this->faker->randomFloat(2, 0, 1000);

        return [
            'user_id'              => User::factory(),
            'balance_in_operation' => $inOperation,
            'balance_total'        => $inOperation,
        ];
    }

    public function empty(): static
    {
        return $this->state([
            'balance_in_operation' => 0.0,
            'balance_total'        => 0.0,
        ]);
    }
}
