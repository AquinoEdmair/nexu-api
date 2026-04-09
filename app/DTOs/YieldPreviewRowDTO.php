<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class YieldPreviewRowDTO
{
    public function __construct(
        public string $userId,
        public string $userName,
        public string $userEmail,
        public string $balanceBefore,    // decimal string
        public string $amountToApply,    // decimal string, can be negative
        public string $balanceAfter,     // decimal string, can be negative
        public bool   $wouldGoNegative,
        public bool   $wouldBeSkipped,   // true if policy=skip and wouldGoNegative
    ) {}
}
