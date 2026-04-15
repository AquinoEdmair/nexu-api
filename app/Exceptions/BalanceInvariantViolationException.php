<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class BalanceInvariantViolationException extends RuntimeException
{
    public function __construct(string $userId, string $available, string $inOperation, string $total)
    {
        parent::__construct(
            "Balance invariant violated for user {$userId}: " .
            "balance_total ({$total}) != balance_in_operation ({$inOperation})"
        );
    }
}
