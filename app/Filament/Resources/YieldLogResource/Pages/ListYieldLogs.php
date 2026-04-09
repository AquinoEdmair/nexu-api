<?php

declare(strict_types=1);

namespace App\Filament\Resources\YieldLogResource\Pages;

use App\Filament\Resources\YieldLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListYieldLogs extends ListRecords
{
    protected static string $resource = YieldLogResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva aplicación'),
        ];
    }
}
