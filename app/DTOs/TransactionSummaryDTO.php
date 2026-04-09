<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TransactionSummaryDTO
{
    public function __construct(
        public string $totalDeposits,
        public string $totalWithdrawals,
        public string $totalYields,
        public string $totalCommissions,
        public string $totalReferralCommissions,
        public int    $transactionCount,
    ) {}
}
