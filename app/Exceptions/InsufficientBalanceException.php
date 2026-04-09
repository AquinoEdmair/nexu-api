<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class InsufficientBalanceException extends DomainException
{
    public function __construct(float $requested, float $available)
    {
        parent::__construct(
            sprintf(
                'Fondos insuficientes: se solicitaron $%.8f pero el saldo disponible es $%.8f.',
                $requested,
                $available
            )
        );
    }
}
