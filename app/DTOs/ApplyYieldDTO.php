<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ApplyYieldDTO
{
    public function __construct(
        public string $type,           // 'percentage' | 'fixed_amount'
        public float $value,          // positive or negative
        public string $scope,          // 'all' | 'specific_user'
        public ?string $userId,         // null if scope='all'
        public ?string $description,
        public string $negativePolicy, // 'floor' | 'skip'
    ) {
    }
}
