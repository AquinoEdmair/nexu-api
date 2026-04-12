<?php

namespace App\Filament\Resources\AdminCommissionLedgerResource\Pages;

use App\Filament\Resources\AdminCommissionLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAdminCommissionLedgers extends ManageRecords
{
    protected static string $resource = AdminCommissionLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
