<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title       = 'Transacciones';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => TransactionResource::typeColor($state))
                    ->formatStateUsing(fn(string $state): string => TransactionResource::typeLabel($state)),

                TextColumn::make('net_amount')
                    ->label('Monto neto')
                    ->numeric(decimalPlaces: 8)
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Moneda'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => TransactionResource::statusColor($state))
                    ->formatStateUsing(fn(string $state): string => TransactionResource::statusLabel($state)),

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
