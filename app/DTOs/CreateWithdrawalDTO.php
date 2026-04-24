<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class CreateWithdrawalDTO
{
    public function __construct(
        public float   $amount,
        public string  $currency,
        public string  $destinationAddress,
        public ?string $qrImagePath = null,
    ) {}
}
