<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Admin;

final class AdminAuthResultDTO
{
    public function __construct(
        public readonly bool    $success,
        public readonly bool    $requiresTwoFactor,
        public readonly ?Admin  $admin,
        public readonly ?string $error,
    ) {}

    public static function success(Admin $admin, bool $requiresTwoFactor = false): self
    {
        return new self(true, $requiresTwoFactor, $admin, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, false, null, $error);
    }
}
