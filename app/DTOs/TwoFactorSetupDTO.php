<?php

declare(strict_types=1);

namespace App\DTOs;

final class TwoFactorSetupDTO
{
    public function __construct(
        public readonly string $qrCodeUrl,
        public readonly string $secretKey,
    ) {}
}
