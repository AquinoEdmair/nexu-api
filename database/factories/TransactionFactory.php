<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $type   = $this->faker->randomElement(['deposit', 'withdrawal', 'commission', 'yield', 'referral_commission']);
        $amount = (string) $this->faker->randomFloat(8, 10, 1000);
        $fee    = (string) round((float) $amount * 0.02, 8);
        $net    = (string) round((float) $amount - (float) $fee, 8);

        return [
            'user_id'        => User::factory(),
            'type'           => $type,
            'amount'         => $amount,
            'fee_amount'     => $fee,
            'net_amount'     => $net,
            'currency'       => $this->faker->randomElement(['USDT', 'BTC', 'ETH']),
            'status'         => $this->faker->randomElement(['pending', 'confirmed', 'rejected', 'processing']),
            'external_tx_id' => $type === 'deposit' ? $this->faker->uuid() : null,
            'metadata'       => null,
            'description'    => null,
            'reference_type' => null,
            'reference_id'   => null,
        ];
    }

    public function deposit(): static
    {
        return $this->state(['type' => 'deposit', 'status' => 'confirmed']);
    }

    public function withdrawal(): static
    {
        return $this->state(['type' => 'withdrawal']);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function forYieldLog(string $yieldLogId): static
    {
        return $this->state([
            'type'           => 'yield',
            'status'         => 'confirmed',
            'reference_type' => 'yield_log',
            'reference_id'   => $yieldLogId,
        ]);
    }
}
