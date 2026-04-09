<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Collection;

final readonly class YieldPreviewDTO
{
    /**
     * @param Collection<int, YieldPreviewRowDTO> $userRows
     */
    public function __construct(
        public int        $totalUsers,
        public string     $totalAmountToApply,   // decimal string, can be negative
        public string     $systemBalanceBefore,
        public string     $systemBalanceAfter,
        public Collection $userRows,             // max 100 rows for display
        public bool       $hasUsersGoingNegative,
        public int        $usersSkippedByPolicy,
    ) {}
}
