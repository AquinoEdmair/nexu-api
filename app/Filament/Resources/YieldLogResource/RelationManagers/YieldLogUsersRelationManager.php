<?php

declare(strict_types=1);

namespace App\Filament\Resources\YieldLogResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class YieldLogUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'yieldLogUsers';
    protected static ?string $title       = 'Detalle por usuario';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')
                    ->label('Usuario')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('balance_before')
                    ->label('Balance antes')
                    ->formatStateUsing(fn(string $state): string => TransactionResource::formatSmart($state))
                    ->prefix('$'),

                TextColumn::make('amount_applied')
                    ->label('Monto aplicado')
                    ->formatStateUsing(fn(string $state): string => TransactionResource::formatSmart($state))
                    ->prefix('$')
                    ->color(fn(string $state): string => (float) $state < 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('balance_after')
                    ->label('Balance después')
                    ->formatStateUsing(fn(string $state): string => TransactionResource::formatSmart($state))
                    ->prefix('$'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'applied' => 'success',
                        'skipped' => 'warning',
                        'failed'  => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'applied' => 'Aplicado',
                        'skipped' => 'Omitido',
                        'failed'  => 'Fallido',
                        default   => $state,
                    }),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(50)
                    ->tooltip(fn(?string $state): ?string => $state)
                    ->wrap()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('amount_applied', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'applied' => 'Aplicado',
                        'skipped' => 'Omitido',
                        'failed'  => 'Fallido',
                    ]),
            ])
            ->paginated([25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
