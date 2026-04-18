<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalCurrencyResource\Pages;

use App\Filament\Resources\WithdrawalCurrencyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditWithdrawalCurrency extends EditRecord
{
    protected static string $resource = WithdrawalCurrencyResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
