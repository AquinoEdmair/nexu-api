<?php

declare(strict_types=1);

namespace App\DTOs;

final class DailyVolumeDTO
{
    public function __construct(
        public readonly string $day,   // 'YYYY-MM-DD'
        public readonly float  $total,
        public readonly int    $count,
    ) {}
}
