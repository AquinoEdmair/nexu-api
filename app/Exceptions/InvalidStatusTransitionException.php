<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(
            "Transición de estado inválida: '{$from}' → '{$to}'."
        );
    }
}
