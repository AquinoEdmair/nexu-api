<?php

declare(strict_types=1);

namespace App\Filament\Resources\EliteTierResource\Pages;

use App\Filament\Resources\EliteTierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditEliteTier extends EditRecord
{
    protected static string $resource = EliteTierResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
