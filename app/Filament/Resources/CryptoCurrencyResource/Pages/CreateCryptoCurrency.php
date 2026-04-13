<?php

declare(strict_types=1);

namespace App\Filament\Resources\CryptoCurrencyResource\Pages;

use App\Filament\Resources\CryptoCurrencyResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCryptoCurrency extends CreateRecord
{
    protected static string $resource = CryptoCurrencyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
