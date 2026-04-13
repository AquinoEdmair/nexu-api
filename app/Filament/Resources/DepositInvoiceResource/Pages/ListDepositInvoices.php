<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositInvoiceResource\Pages;

use App\Filament\Resources\DepositInvoiceResource;
use Filament\Resources\Pages\ListRecords;

final class ListDepositInvoices extends ListRecords
{
    protected static string $resource = DepositInvoiceResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
