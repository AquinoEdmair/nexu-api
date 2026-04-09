<?php

declare(strict_types=1);

namespace App\DTOs;

final class MetricsOverviewDTO
{
    public function __construct(
        public readonly int   $activeUsers,
        public readonly float $depositsToday,
        public readonly int   $pendingWithdrawals,
        public readonly float $systemBalanceTotal,
    ) {}
}
