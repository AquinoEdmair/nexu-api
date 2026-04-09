<?php

declare(strict_types=1);

namespace App\Filament\Resources\YieldLogResource\Pages;

use App\Filament\Resources\YieldLogResource;
use App\Models\YieldLog;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

final class ViewYieldLog extends ViewRecord
{
    protected static string $resource = YieldLogResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make('Datos de la aplicación')
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
                            ->color(fn(string $state): string => YieldLogResource::typeColor($state))
                            ->formatStateUsing(fn(string $state): string => YieldLogResource::typeLabel($state)),

                        TextEntry::make('value')
                            ->label('Valor')
                            ->formatStateUsing(fn(string $state, YieldLog $record): string => $record->type === 'percentage'
                                ? number_format((float) $state, 4) . '%'
                                : '$' . number_format((float) $state, 2)
                            ),

                        TextEntry::make('scope')
                            ->label('Alcance')
                            ->badge()
                            ->color(fn(string $state): string => $state === 'all' ? 'gray' : 'warning')
                            ->formatStateUsing(fn(string $state): string => $state === 'all'
                                ? 'Todos los usuarios activos'
                                : 'Usuario específico'
                            ),

                        TextEntry::make('negative_policy')
                            ->label('Política de negativos')
                            ->badge()
                            ->color(fn(string $state): string => $state === 'floor' ? 'info' : 'warning')
                            ->formatStateUsing(fn(string $state): string => $state === 'floor'
                                ? 'Aplicar hasta $0'
                                : 'Omitir usuarios afectados'
                            ),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpan(2),

                        TextEntry::make('appliedBy.name')
                            ->label('Admin que aplicó'),

                        TextEntry::make('applied_at')
                            ->label('Aplicado el')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('completed_at')
                            ->label('Completado el')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('Pendiente'),
                    ]),

                Section::make('Estado y resultados')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn(string $state): string => YieldLogResource::statusColor($state))
                            ->formatStateUsing(fn(string $state): string => YieldLogResource::statusLabel($state)),

                        TextEntry::make('users_count')
                            ->label('Usuarios afectados')
                            ->numeric(),

                        TextEntry::make('total_applied')
                            ->label('Total aplicado')
                            ->numeric(decimalPlaces: 8)
                            ->prefix('$'),

                        TextEntry::make('error_message')
                            ->label('Error')
                            ->placeholder('—')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn(YieldLog $record): bool => $record->status === 'failed'),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): YieldLog
    {
        /** @var YieldLog */
        return YieldLog::with('appliedBy:id,name,email')->findOrFail($key);
    }
}
