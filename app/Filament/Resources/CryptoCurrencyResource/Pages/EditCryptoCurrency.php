<?php

declare(strict_types=1);

namespace App\Filament\Resources\CryptoCurrencyResource\Pages;

use App\Filament\Resources\CryptoCurrencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCryptoCurrency extends EditRecord
{
    protected static string $resource = CryptoCurrencyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
