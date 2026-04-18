<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalCurrencyResource\Pages;

use App\Filament\Resources\WithdrawalCurrencyResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateWithdrawalCurrency extends CreateRecord
{
    protected static string $resource = WithdrawalCurrencyResource::class;
}
