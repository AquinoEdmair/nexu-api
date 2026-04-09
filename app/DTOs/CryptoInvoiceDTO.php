<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Carbon;

final readonly class CryptoInvoiceDTO
{
    public function __construct(
        public string  $invoiceId,
        public string  $address,
        public string  $currency,
        public ?string $network,
        public ?string $qrCodeUrl,
        public Carbon  $expiresAt,
    ) {}
}
