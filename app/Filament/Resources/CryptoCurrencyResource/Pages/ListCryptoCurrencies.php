<?php

declare(strict_types=1);

namespace App\Filament\Resources\CryptoCurrencyResource\Pages;

use App\Filament\Resources\CryptoCurrencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCryptoCurrencies extends ListRecords
{
    protected static string $resource = CryptoCurrencyResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
