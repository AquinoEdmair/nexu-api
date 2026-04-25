<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositRequestResource\Pages;

use App\Filament\Resources\DepositRequestResource;
use Filament\Resources\Pages\ListRecords;

final class ListDepositRequests extends ListRecords
{
    protected static string $resource = DepositRequestResource::class;
}
