<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class InvestmentService
{
    /**
     * Deprecated: all deposits go directly to balance_in_operation.
     * The concept of moving funds from available to in_operation no longer exists.
     *
     * @throws \RuntimeException always — endpoint returns 410 in the controller
     */
    public function invest(User $user, float $amount): Transaction
    {
        throw new \RuntimeException('El concepto de balance disponible fue eliminado. Todo el saldo ya está en operación.');
    }
}
