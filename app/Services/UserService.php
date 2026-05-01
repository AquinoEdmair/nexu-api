<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\UserProfileDTO;
use App\Events\UserCreatedByAdmin;
use App\Events\UserStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\CommissionConfig;
use App\Models\Referral;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class UserService
{
    /** @var array<string, array<string>> */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['active'],
        'active'  => ['blocked'],
        'blocked' => ['active'],
    ];

    private const DEFAULT_COMMISSION_RATE = '0.0500';

    private const ELITE_PLATINUM_THRESHOLD = 20000;
    private const ELITE_GOLD_THRESHOLD     = 5000;
    private const ELITE_SILVER_THRESHOLD   = 1000;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, referred_by_code?: string|null} $data
     * @throws \Illuminate\Database\QueryException
     */
    public function createUser(array $data, Admin $admin): User
    {
        $tempPassword = Str::password(12);

        $user = DB::transaction(function () use ($data, $tempPassword): User {
            $referrer = $this->resolveReferrer($data['referred_by_code'] ?? null);

            $user = User::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'phone'             => $data['phone'] ?? null,
                'password'          => Hash::make($tempPassword),
                'referral_code'     => $this->generateUniqueReferralCode(),
                'referred_by'       => $referrer?->id,
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            Wallet::create([
                'user_id'              => $user->id,
                'balance_in_operation' => 0,
                'balance_total'        => 0,
            ]);

            if ($referrer !== null) {
                $rate = $this->getReferralCommissionRate();
                Referral::create([
                    'referrer_id'     => $referrer->id,
                    'referred_id'     => $user->id,
                    'commission_rate' => $rate,
                    'total_earned'    => 0,
                ]);
            }

            return $user;
        });

        activity()
            ->causedBy($admin)
            ->performedOn($user)
            ->withProperties(['email' => $user->email])
            ->log("Admin {$admin->name} creó usuario {$user->email}");

        UserCreatedByAdmin::dispatch($user, $admin, $tempPassword);

        return $user;
    }

    /**
     * @param  array{name?: string, phone?: string|null} $data
     */
    public function updateProfile(User $user, array $data, Admin $admin): User
    {
        $user->update([
            'name'  => $data['name'] ?? $user->name,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $user->phone,
        ]);

        activity()
            ->causedBy($admin)
            ->performedOn($user)
            ->withProperties(['changes' => $data])
            ->log("Admin {$admin->name} editó perfil de {$user->email}");

        return $user->fresh();
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function updateStatus(
        User   $user,
        string $newStatus,
        string $reason,
        Admin  $admin,
    ): User {
        $oldStatus = $user->status;

        $this->assertTransitionAllowed($oldStatus, $newStatus);

        $user->update([
            'status'         => $newStatus,
            'blocked_reason' => $newStatus === 'blocked' ? $reason : null,
            'blocked_at'     => $newStatus === 'blocked' ? now() : null,
        ]);

        $action = $newStatus === 'blocked' ? 'bloqueó' : 'desbloqueó';

        activity()
            ->causedBy($admin)
            ->performedOn($user)
            ->withProperties(['reason' => $reason, 'old_status' => $oldStatus, 'new_status' => $newStatus])
            ->log("Admin {$admin->name} {$action} a {$user->email}: {$reason}");

        UserStatusChanged::dispatch($user, $oldStatus, $newStatus, $reason, $admin);

        return $user->fresh();
    }

    public function resetPassword(User $user, Admin $admin): void
    {
        $tempPassword = Str::password(12);

        $user->update(['password' => Hash::make($tempPassword)]);

        activity()
            ->causedBy($admin)
            ->performedOn($user)
            ->log("Admin {$admin->name} reseteó password de {$user->email}");

        $this->notifications->sendTemporaryPassword($user, $tempPassword);
    }

    public function getProfile(string $userId): UserProfileDTO
    {
        /** @var User $user */
        $user = User::with([
            'wallet',
            'referrer',
            'referrals.referred',
        ])->findOrFail($userId);

        $recentTransactions = $user->transactions()
            ->latest()
            ->limit(10)
            ->get();

        $totalPoints   = (string) $user->elitePoints()->active()->sum('points');
        $totalEarnings = (string) $user->referrals()->sum('total_earned');

        return new UserProfileDTO(
            user:                  $user,
            wallet:                $user->wallet,
            recentTransactions:    $recentTransactions,
            referredBy:            $user->referrer,
            referrals:             $user->referrals,
            totalElitePoints:      $totalPoints,
            eliteLevel:            $this->resolveEliteLevel((float) $totalPoints),
            totalReferralEarnings: $totalEarnings,
        );
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /** @throws InvalidStatusTransitionException */
    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, strict: true)) {
            throw new InvalidStatusTransitionException($from, $to);
        }
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function resolveReferrer(?string $referralCode): ?User
    {
        if (blank($referralCode)) {
            return null;
        }

        return User::where('referral_code', $referralCode)->first();
    }

    private function getReferralCommissionRate(): string
    {
        $config = CommissionConfig::where('type', 'referral')
            ->where('is_active', true)
            ->latest()
            ->first();

        return $config?->value ?? self::DEFAULT_COMMISSION_RATE;
    }

    private function resolveEliteLevel(float $points): string
    {
        return match (true) {
            $points >= self::ELITE_PLATINUM_THRESHOLD => 'Platino',
            $points >= self::ELITE_GOLD_THRESHOLD     => 'Oro',
            $points >= self::ELITE_SILVER_THRESHOLD   => 'Plata',
            default                                   => 'Bronce',
        };
    }
}
