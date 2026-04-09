<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ElitePointsRelationManager extends RelationManager
{
    protected static string $relationship = 'elitePoints';
    protected static ?string $title       = 'Puntos Elite';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('points')
                    ->label('Puntos')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->wrap(),

                TextColumn::make('transaction.type')
                    ->label('Transacción origen')
                    ->placeholder('—')
                    ->formatStateUsing(fn(?string $state): string => $state !== null
                        ? TransactionResource::typeLabel($state)
                        : '—'
                    ),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
