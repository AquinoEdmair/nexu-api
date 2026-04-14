<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AdminAdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title       = 'Ajustes de balance (admin)';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'admin_adjustment'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('net_amount')
                    ->label('Monto')
                    ->formatStateUsing(fn(string $state): string =>
                        (float)$state >= 0
                            ? '+$' . number_format(abs((float)$state), 2)
                            : '-$' . number_format(abs((float)$state), 2)
                    )
                    ->color(fn(string $state): string => (float)$state >= 0 ? 'success' : 'danger'),

                TextColumn::make('metadata->field_adjusted')
                    ->label('Campo')
                    ->formatStateUsing(fn(?string $state): string => match($state) {
                        'balance_available'    => 'Disponible',
                        'balance_in_operation' => 'En operación',
                        default                => $state ?? '—',
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label('Motivo')
                    ->wrap()
                    ->limit(80),

                TextColumn::make('metadata->admin_id')
                    ->label('Admin ID')
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('metadata->previous_value')
                    ->label('Valor anterior')
                    ->formatStateUsing(fn(?string $state): string => $state ? '$' . number_format((float)$state, 2) : '—')
                    ->color('gray'),

                TextColumn::make('metadata->new_value')
                    ->label('Valor nuevo')
                    ->formatStateUsing(fn(?string $state): string => $state ? '$' . number_format((float)$state, 2) : '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
