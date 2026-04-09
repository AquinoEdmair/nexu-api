<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionConfigResource\Pages;

use App\Filament\Resources\CommissionConfigResource;
use App\Models\CommissionConfig;
use App\Services\CommissionService;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewCommissionConfig extends ViewRecord
{
    protected static string $resource = CommissionConfigResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (CommissionConfig $record): bool =>
                    ! $record->is_active && Gate::allows('update', $record)
                )
                ->requiresConfirmation()
                ->modalHeading('¿Activar esta configuración?')
                ->modalDescription(fn (CommissionConfig $record): string =>
                    "Se activará la comisión de {$record->type} al "
                    . number_format((float) $record->value, 4)
                    . "%. La configuración activa actual del mismo tipo será desactivada. "
                    . "Las transacciones anteriores NO se verán afectadas."
                )
                ->action(function (CommissionConfig $record, CommissionService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->activate($record, $admin);
                    $this->refreshFormData(['is_active']);
                    Notification::make()
                        ->title('Configuración activada correctamente.')
                        ->success()
                        ->send();
                }),

            Action::make('deactivate')
                ->label('Desactivar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (CommissionConfig $record): bool =>
                    $record->is_active && Gate::allows('update', $record)
                )
                ->requiresConfirmation()
                ->modalHeading('⚠ ¿Desactivar esta configuración?')
                ->modalDescription(fn (CommissionConfig $record): string =>
                    "ATENCIÓN: Si desactivas esta configuración, NO habrá tasa de comisión activa "
                    . "para '{$record->type}'. Los depósitos/referidos futuros NO cobrarán comisión "
                    . "hasta que se active una nueva configuración. ¿Estás seguro?"
                )
                ->action(function (CommissionConfig $record, CommissionService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->deactivate($record, $admin);
                    $this->refreshFormData(['is_active']);
                    Notification::make()
                        ->title('Configuración desactivada. La tasa de comisión es ahora 0%.')
                        ->warning()
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make('Configuración')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (string $state): string => CommissionConfigResource::typeColor($state))
                            ->formatStateUsing(fn (string $state): string => CommissionConfigResource::typeLabel($state)),

                        TextEntry::make('value')
                            ->label('Valor')
                            ->suffix('%')
                            ->numeric(decimalPlaces: 4),

                        IconEntry::make('is_active')
                            ->label('Estado')
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('gray')
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpan(2),

                        TextEntry::make('created_at')
                            ->label('Fecha de creación')
                            ->dateTime('d/m/Y H:i:s'),
                    ]),

                Section::make('Auditoría')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label('Creada por'),

                        TextEntry::make('createdBy.email')
                            ->label('Email del admin'),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): CommissionConfig
    {
        /** @var CommissionConfig */
        return CommissionConfig::with('createdBy:id,name,email')->findOrFail($key);
    }
}
