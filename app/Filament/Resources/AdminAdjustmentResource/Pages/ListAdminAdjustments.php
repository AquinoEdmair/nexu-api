<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminAdjustmentResource\Pages;

use App\Filament\Resources\AdminAdjustmentResource;
use Filament\Resources\Pages\ListRecords;

final class ListAdminAdjustments extends ListRecords
{
    protected static string $resource = AdminAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
