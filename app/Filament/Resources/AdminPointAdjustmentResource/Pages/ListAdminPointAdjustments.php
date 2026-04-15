<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminPointAdjustmentResource\Pages;

use App\Filament\Resources\AdminPointAdjustmentResource;
use Filament\Resources\Pages\ListRecords;

final class ListAdminPointAdjustments extends ListRecords
{
    protected static string $resource = AdminPointAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
