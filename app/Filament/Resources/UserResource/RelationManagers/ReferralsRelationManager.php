<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';
    protected static ?string $title       = 'Referidos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referred.name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('referred.email')
                    ->label('Email')
                    ->copyable(),

                TextColumn::make('commission_rate')
                    ->label('Tasa de comisión')
                    ->formatStateUsing(fn(string $state): string => number_format((float) $state * 100, 2) . '%'),

                TextColumn::make('total_earned')
                    ->label('Comisiones ganadas')
                    ->numeric(decimalPlaces: 8)
                    ->prefix('$'),

                TextColumn::make('created_at')
                    ->label('Fecha de registro')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
