<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title       = 'Transacciones';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => TransactionResource::typeColor($state))
                    ->formatStateUsing(fn (string $state): string => TransactionResource::typeLabel($state)),

                TextColumn::make('amount')
                    ->label('Bruto')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fee_amount')
                    ->label('Comisión')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_amount')
                    ->label('Neto')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable()
                    ->color(fn ($record): string => (float) $record->net_amount >= 0 ? 'success' : 'danger'),

                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => TransactionResource::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => TransactionResource::statusLabel($state)),

                TextColumn::make('external_tx_id')
                    ->label('TX / Referencia')
                    ->copyable()
                    ->fontFamily('mono')
                    ->limit(20)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'deposit'             => 'Depósito',
                        'withdrawal'          => 'Retiro',
                        'yield'               => 'Rendimiento',
                        'commission'          => 'Comisión',
                        'referral_commission' => 'Comisión referido',
                        'admin_adjustment'    => 'Ajuste admin',
                    ]),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'confirmed'  => 'Confirmado',
                        'completed'  => 'Completado',
                        'pending'    => 'Pendiente',
                        'rejected'   => 'Rechazado',
                        'processing' => 'En proceso',
                    ]),
            ])
            ->paginated([15, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
