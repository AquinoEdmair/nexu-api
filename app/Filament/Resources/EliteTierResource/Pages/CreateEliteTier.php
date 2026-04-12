<?php

declare(strict_types=1);

namespace App\Filament\Resources\EliteTierResource\Pages;

use App\Filament\Resources\EliteTierResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEliteTier extends CreateRecord
{
    protected static string $resource = EliteTierResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
