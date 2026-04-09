<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionConfigResource\Pages;

use App\Filament\Resources\CommissionConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCommissionConfigs extends ListRecords
{
    protected static string $resource = CommissionConfigResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva configuración')
                ->visible(fn (): bool => auth()->user()?->role === 'super_admin'),
        ];
    }
}
