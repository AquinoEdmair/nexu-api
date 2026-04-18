<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalCurrencyResource\Pages;

use App\Filament\Resources\WithdrawalCurrencyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListWithdrawalCurrencies extends ListRecords
{
    protected static string $resource = WithdrawalCurrencyResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
