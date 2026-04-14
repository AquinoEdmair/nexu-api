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
    protected static ?string $title       = 'Puntos Élite';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('points')
                    ->label('Puntos')
                    ->formatStateUsing(fn(string $state): string =>
                        (float)$state >= 0
                            ? '+' . number_format((float)$state, 2)
                            : number_format((float)$state, 2)
                    )
                    ->color(fn(string $state): string => (float)$state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('source')
                    ->label('Origen')
                    ->state(fn($record): string =>
                        str_starts_with((string)$record->description, 'admin:') ? 'Ajuste admin' : 'Sistema'
                    )
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Ajuste admin' ? 'warning' : 'info'),

                TextColumn::make('description')
                    ->label('Detalle')
                    ->formatStateUsing(function (string $state): string {
                        if (str_starts_with($state, 'admin:')) {
                            $parts = explode(':', $state, 3);
                            $adminId = $parts[1] ?? '?';
                            $reason  = $parts[2] ?? '—';
                            return "Admin #{$adminId} — {$reason}";
                        }
                        return $state;
                    })
                    ->wrap()
                    ->limit(100),

                TextColumn::make('transaction.type')
                    ->label('Transacción origen')
                    ->placeholder('—')
                    ->formatStateUsing(fn(?string $state): string => $state !== null
                        ? TransactionResource::typeLabel($state)
                        : '—'
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
