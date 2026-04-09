<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TransactionFilterDTO
{
    /**
     * @param string[]|null $types
     * @param string[]|null $statuses
     */
    public function __construct(
        public ?array   $types     = null,
        public ?array   $statuses  = null,
        public ?string  $currency  = null,
        public ?string  $dateFrom  = null,
        public ?string  $dateTo    = null,
        public ?float   $amountMin = null,
        public ?float   $amountMax = null,
        public ?string  $userId    = null,
        public ?string  $search    = null,
        public int      $perPage   = 25,
    ) {}
}
