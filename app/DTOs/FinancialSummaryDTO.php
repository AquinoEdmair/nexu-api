<?php

declare(strict_types=1);

namespace App\DTOs;

final class FinancialSummaryDTO
{
    public function __construct(
        public readonly float $totalDeposited,
        public readonly float $totalWithdrawn,
        public readonly float $totalYieldApplied,
        public readonly float $totalCommissions,
        public readonly float $totalReferralCommissions,
        public readonly float $totalAdminAdjustments,
        public readonly int   $usersWithBalance,
    ) {}
}
