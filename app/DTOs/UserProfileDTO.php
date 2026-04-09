<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Collection;

final readonly class UserProfileDTO
{
    public function __construct(
        public User       $user,
        public ?Wallet    $wallet,
        public Collection $recentTransactions,
        public ?User      $referredBy,
        public Collection $referrals,
        public string     $totalElitePoints,
        public string     $eliteLevel,
        public string     $totalReferralEarnings,
    ) {}
}
