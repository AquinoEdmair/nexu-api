<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalLockResource\Pages;

use App\Filament\Resources\WithdrawalLockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageWithdrawalLocks extends ManageRecords
{
    protected static string $resource = WithdrawalLockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Crear Candado de Retiro'),
        ];
    }
}
