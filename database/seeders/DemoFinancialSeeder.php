<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EliteTier;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\ElitePoint;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DemoFinancialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Obtener o crear niveles Élite
            $tierBronce = EliteTier::where('slug', 'bronce')->first() ?? EliteTier::create([
                'name' => 'Bronce',
                'slug' => 'bronce',
                'min_points' => 0,
                'multiplier' => '1.00',
                'first_deposit_commission_rate' => '0.0500',
                'recurring_commission_rate' => '0.0200',
                'sort_order' => 1,
                'is_active' => true,
            ]);

            $tierOro = EliteTier::where('slug', 'oro')->first() ?? EliteTier::create([
                'name' => 'Oro',
                'slug' => 'oro',
                'min_points' => 5000,
                'multiplier' => '1.50',
                'first_deposit_commission_rate' => '0.1000',
                'recurring_commission_rate' => '0.0500',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            // Crear usuarios simulados
            $usersToSimulate = [
                ['name' => 'Esteban Castañeda', 'email' => 'Stv7@live.com'],
                ['name' => 'Omar Díaz', 'email' => 'ing.omar12@hotmail.com'],
                ['name' => 'Diksan Hernandez', 'email' => 'arley0031@gmail.com'],
            ];

            foreach ($usersToSimulate as $u) {
                $user = $this->createUser(
                    name: $u['name'],
                    email: $u['email'],
                    tierId: $tierOro->id,
                    phone: $this->generateColombiaPhone(),
                    createdAt: Carbon::create(2025, 9, rand(1, 15), rand(8, 20), rand(0, 59))
                );

                $this->generateHistoricalTimeline($user);
            }
        });
    }

    private function generateHistoricalTimeline(User $user): void
    {
        // 📅 Septiembre 2025
        $this->createDeposit($user, 5000, Carbon::create(2025, 9, rand(16, 20)));
        $this->generateYields($user, Carbon::create(2025, 9, 21), Carbon::create(2025, 9, 30), 1000, 5);

        // 📅 Octubre 2025
        $this->createDeposit($user, 12000, Carbon::create(2025, 10, rand(5, 10)));
        $this->generateYields($user, Carbon::create(2025, 10, 11), Carbon::create(2025, 10, 31), 2000, 8);

        // 📅 Noviembre 2025
        $this->generateYields($user, Carbon::create(2025, 11, 1), Carbon::create(2025, 11, 30), 2000, 10);

        // 📅 Diciembre 2025
        $this->createWithdrawal($user, 10000, Carbon::create(2025, 12, rand(10, 20)));

        // 📅 Enero 2026
        $this->createDeposit($user, 35000, Carbon::create(2026, 1, rand(5, 15)));
        $this->generateYields($user, Carbon::create(2026, 1, 16), Carbon::create(2026, 1, 31), 3000, 8);

        // 📅 Febrero - Inicios Marzo 2026
        $this->generateYields($user, Carbon::create(2026, 2, 1), Carbon::create(2026, 3, 15), 20000, 20);

        // 📅 Finales de Marzo 2026
        $this->createWithdrawal($user, 20000, Carbon::create(2026, 3, rand(20, 28)));

        // 📅 Inicios de Abril 2026
        $this->createWithdrawal($user, 20000, Carbon::create(2026, 4, rand(1, 10)));

        // 📅 20 de Abril 2026 (Incremento fuerte para dejar el balance final ~80k-90k)
        $this->createDeposit($user, 50000, Carbon::create(2026, 4, 20));
        $this->generateYields($user, Carbon::create(2026, 4, 21), Carbon::create(2026, 4, 28), 5000, 6);
    }

    private function generateYields(User $user, Carbon $startDate, Carbon $endDate, float $totalAmount, int $txCount): void
    {
        $avg = $totalAmount / $txCount;
        for ($i = 0; $i < $txCount; $i++) {
            $variance = $avg * 0.4;
            $amount = $avg + (rand(-100, 100) / 100) * $variance;
            
            // Repartir aleatoriamente los días
            $diffInDays = $startDate->diffInDays($endDate);
            if ($diffInDays < 1) $diffInDays = 1;
            
            $randomDay = $startDate->copy()->addDays(rand(0, (int) $diffInDays));
            
            $this->createYield($user, round($amount, 2), $randomDay);
        }
    }

    private function generateColombiaPhone(): string
    {
        return '+573' . rand(0, 2) . rand(0, 9) . rand(1000000, 9999999);
    }

    private function createUser(string $name, string $email, string $tierId, ?string $phone = null, ?Carbon $createdAt = null): User
    {
        $date = $createdAt ?? now();
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'password',
            'referral_code' => strtoupper(Str::random(8)),
            'status' => 'active',
            'elite_tier_id' => $tierId,
            'email_verified_at' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $user->wallet()->create([
            'balance_in_operation' => '0.00000000',
            'balance_total' => '0.00000000',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        return $user;
    }

    private function createDeposit(User $user, float $amount, ?Carbon $date = null): Transaction
    {
        $date = $date ?? now();
        $wallet = $user->wallet;
        $amountStr = number_format($amount, 8, '.', '');
        
        $wallet->increment('balance_in_operation', $amount);
        $wallet->increment('balance_total', $amount);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => $amountStr,
            'fee_amount' => '0.00000000',
            'net_amount' => $amountStr,
            'currency' => 'USDT',
            'status' => 'confirmed',
            'external_tx_id' => 'manual-' . Str::random(12),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Puntos Elite por depósito
        $multiplier = (float) ($user->eliteTier->multiplier ?? 1.0);
        ElitePoint::create([
            'user_id' => $user->id,
            'points' => number_format($amount * $multiplier, 2, '.', ''),
            'transaction_id' => $tx->id,
            'description' => "deposit:{$tx->id}",
            'created_at' => $date,
            'updated_at' => $date,
            'expires_at' => $date->copy()->addMonths(12)->endOfMonth()->toDateString(),
        ]);

        return $tx;
    }

    private function createYield(User $user, float $amount, ?Carbon $date = null): void
    {
        $date = $date ?? now();
        $wallet = $user->wallet;
        $amountStr = number_format($amount, 8, '.', '');

        $wallet->increment('balance_in_operation', $amount);
        $wallet->increment('balance_total', $amount);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'yield',
            'amount' => $amountStr,
            'fee_amount' => '0.00000000',
            'net_amount' => $amountStr,
            'currency' => 'USD',
            'status' => 'confirmed',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $multiplier = (float) ($user->eliteTier->multiplier ?? 1.0);
        ElitePoint::create([
            'user_id' => $user->id,
            'points' => number_format($amount * $multiplier, 2, '.', ''),
            'transaction_id' => $tx->id,
            'description' => "yield:demo-log",
            'created_at' => $date,
            'updated_at' => $date,
            'expires_at' => $date->copy()->addMonths(12)->endOfMonth()->toDateString(),
        ]);
    }

    private function createWithdrawal(User $user, float $amount, ?Carbon $date = null): void
    {
        $date = $date ?? now();
        // Assuming no fee to not complicate the exact withdrawal amount vs balance, or keep 5% fee but make the request amount exact.
        $fee = $amount * 0.05; 
        $net = $amount - $fee;

        $wallet = $user->wallet;
        $wallet->decrement('balance_in_operation', $amount);
        $wallet->decrement('balance_total', $amount);

        $request = WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => number_format($amount, 8, '.', ''),
            'fee_amount' => number_format($fee, 8, '.', ''),
            'net_amount' => number_format($net, 8, '.', ''),
            'currency' => 'USDT',
            'destination_address' => '0x' . Str::random(40),
            'status' => 'completed',
            'tx_hash' => '0x' . Str::random(64),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'withdrawal',
            'amount' => number_format($amount, 8, '.', ''),
            'fee_amount' => number_format($fee, 8, '.', ''),
            'net_amount' => '-' . number_format($net, 8, '.', ''),
            'currency' => 'USDT',
            'status' => 'confirmed',
            'reference_type' => 'withdrawal_request',
            'reference_id' => $request->id,
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }
}
