<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositCurrencyResource\Pages;

use App\Filament\Resources\DepositCurrencyResource;
use Filament\Resources\Pages\ListRecords;

final class ListDepositCurrencies extends ListRecords
{
    protected static string $resource = DepositCurrencyResource::class;
}
