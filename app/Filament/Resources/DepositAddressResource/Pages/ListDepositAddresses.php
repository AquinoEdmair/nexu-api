<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositAddressResource\Pages;

use App\Filament\Resources\DepositAddressResource;
use Filament\Resources\Pages\ListRecords;

final class ListDepositAddresses extends ListRecords
{
    protected static string $resource = DepositAddressResource::class;
}
