<?php

declare(strict_types=1);

namespace App\Filament\Resources\EliteTierResource\Pages;

use App\Filament\Resources\EliteTierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEliteTiers extends ListRecords
{
    protected static string $resource = EliteTierResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
