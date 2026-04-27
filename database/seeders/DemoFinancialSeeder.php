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

            // 2. Crear 3 Usuarios en cadena de referidos
            // User A (Inversor PRO)
            $userA = $this->createUser('User A Pro', 'usera@example.com', $tierOro->id);
            
            // User B (Referido por A)
            $userB = $this->createUser('User B Junior', 'userb@example.com', $tierBronce->id, $userA->referral_code);
            
            // User C (Referido por B)
            $userC = $this->createUser('User C Newbie', 'userc@example.com', $tierBronce->id, $userB->referral_code);

            // 3. Generar movimientos para User A
            $this->createDeposit($userA, 12000.00); // Depósito grande
            $this->createYield($userA, 450.20);     // Rendimiento

            // 4. Generar movimientos para User B
            $depB1 = $this->createDeposit($userB, 2500.00);
            $this->applyReferralCommission($userA, $userB, $depB1, true); // Comisión para A (primera vez)

            $this->createYield($userB, 125.50);

            $depB2 = $this->createDeposit($userB, 1000.00);
            $this->applyReferralCommission($userA, $userB, $depB2, false); // Comisión para A (recurrente)

            // 5. Generar movimientos para User C
            $depC1 = $this->createDeposit($userC, 500.00);
            $this->applyReferralCommission($userB, $userC, $depC1, true); // Comisión para B

            $this->createWithdrawal($userC, 100.00); // Retiro de C

            // 6. Algunos ajustes de admin para ver diversidad
            $this->createAdminAdjustment($userA, 1000.00, 'Bono de bienvenida');
        });
    }

    private function createUser(string $name, string $email, string $tierId, ?string $referrerCode = null): User
    {
        $referrerId = null;
        if ($referrerCode) {
            $referrer = User::where('referral_code', $referrerCode)->first();
            $referrerId = $referrer?->id;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password', // hashed by model cast
            'referral_code' => strtoupper(Str::random(8)),
            'referred_by' => $referrerId,
            'status' => 'active',
            'elite_tier_id' => $tierId,
            'email_verified_at' => now(),
        ]);

        $user->wallet()->create([
            'balance_in_operation' => '0.00000000',
            'balance_total' => '0.00000000',
        ]);

        if ($referrerId) {
            Referral::create([
                'referrer_id' => $referrerId,
                'referred_id' => $user->id,
                'commission_rate' => '0.0000',
                'total_earned' => '0.00000000',
            ]);
        }

        return $user;
    }

    private function createDeposit(User $user, float $amount): Transaction
    {
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
            'created_at' => now()->subDays(rand(5, 15)),
        ]);

        // Puntos Elite por depósito
        $multiplier = (float) ($user->eliteTier->multiplier ?? 1.0);
        ElitePoint::create([
            'user_id' => $user->id,
            'points' => number_format($amount * $multiplier, 2, '.', ''),
            'transaction_id' => $tx->id,
            'description' => "deposit:{$tx->id}",
        ]);

        return $tx;
    }

    private function applyReferralCommission(User $referrer, User $referred, Transaction $deposit, bool $isFirst): void
    {
        $tier = $referrer->eliteTier;
        $rate = (float) ($isFirst ? $tier->first_deposit_commission_rate : $tier->recurring_commission_rate);
        $commission = (float) $deposit->net_amount * $rate;

        if ($commission <= 0) return;

        $wallet = $referrer->wallet;
        $commissionStr = number_format($commission, 8, '.', '');

        $wallet->increment('balance_in_operation', $commission);
        $wallet->increment('balance_total', $commission);

        $tx = Transaction::create([
            'user_id' => $referrer->id,
            'wallet_id' => $wallet->id,
            'type' => 'referral_commission',
            'amount' => $commissionStr,
            'fee_amount' => '0.00000000',
            'net_amount' => $commissionStr,
            'currency' => 'USD',
            'status' => 'confirmed',
            'reference_type' => 'deposit',
            'reference_id' => $deposit->id,
            'metadata' => [
                'source_user_id' => $referred->id,
                'deposit_type' => $isFirst ? 'first' : 'recurring',
                'commission_rate' => (string) $rate,
            ],
            'created_at' => $deposit->created_at->addMinutes(10),
        ]);

        // Puntos Elite por comisión de referido
        $multiplier = (float) ($tier->multiplier ?? 1.0);
        ElitePoint::create([
            'user_id' => $referrer->id,
            'points' => number_format($commission * $multiplier, 2, '.', ''),
            'transaction_id' => $tx->id,
            'description' => "referral_commission:{$tx->id}",
            'source_user_id' => $referred->id,
        ]);

        if ($isFirst) {
            Referral::where('referrer_id', $referrer->id)
                ->where('referred_id', $referred->id)
                ->update(['first_deposit_tx_id' => $deposit->id]);
        }

        Referral::where('referrer_id', $referrer->id)
            ->where('referred_id', $referred->id)
            ->increment('total_earned', $commissionStr);
    }

    private function createYield(User $user, float $amount): void
    {
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
            'created_at' => now()->subDays(rand(1, 4)),
        ]);

        $multiplier = (float) ($user->eliteTier->multiplier ?? 1.0);
        ElitePoint::create([
            'user_id' => $user->id,
            'points' => number_format($amount * $multiplier, 2, '.', ''),
            'transaction_id' => $tx->id,
            'description' => "yield:demo-log",
        ]);
    }

    private function createWithdrawal(User $user, float $amount): void
    {
        $fee = $amount * 0.05; // 5% fee
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
            'created_at' => now()->subHours(2),
        ]);
    }

    private function createAdminAdjustment(User $user, float $amount, string $reason): void
    {
        $wallet = $user->wallet;
        $amountStr = number_format($amount, 8, '.', '');

        $wallet->increment('balance_in_operation', $amount);
        $wallet->increment('balance_total', $amount);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'admin_adjustment',
            'amount' => $amountStr,
            'fee_amount' => '0.00000000',
            'net_amount' => $amountStr,
            'currency' => 'USD',
            'status' => 'confirmed',
            'description' => $reason,
            'metadata' => ['admin_id' => 'system'],
            'created_at' => now()->subDays(10),
        ]);

        $multiplier = (float) ($user->eliteTier->multiplier ?? 1.0);
        ElitePoint::create([
            'user_id' => $user->id,
            'points' => number_format($amount * $multiplier, 2, '.', ''),
            'transaction_id' => $tx->id,
            'description' => "admin_adjustment:{$tx->id}",
        ]);
    }
}
