<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\AdminCommissionLedger;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\AdminCommissionLedgerResource\Pages;

final class AdminCommissionLedgerResource extends Resource
{
    protected static ?string $model = AdminCommissionLedger::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Libro de Comisiones';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Libro de Comisiones';
    protected static ?string $pluralModelLabel = 'Libros de Comisiones';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deposit' => 'info',
                        'withdrawal' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'deposit' => 'Depósito',
                        'withdrawal' => 'Retiro',
                        default => $state,
                    }),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto Comisión')
                    ->formatStateUsing(fn(string $state): string => TransactionResource::formatSmart($state))
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('commission_rate')
                    ->label('Tasa (%)')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 2),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('source_type')
                    ->label('Tipo')
                    ->options([
                        'deposit' => 'Depósito',
                        'withdrawal' => 'Retiro',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAdminCommissionLedgers::route('/'),
        ];
    }
}
