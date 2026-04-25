<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DepositCurrencyResource\Pages\ListDepositCurrencies;
use App\Models\DepositCurrency;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class DepositCurrencyResource extends Resource
{
    protected static ?string $model = DepositCurrency::class;

    protected static ?string $navigationIcon        = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel       = 'Monedas depósito';
    protected static ?string $navigationGroup       = 'Depósitos';
    protected static ?int    $navigationSort        = 1;
    protected static ?string $modelLabel            = 'Moneda';
    protected static ?string $pluralModelLabel      = 'Monedas de depósito';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('symbol')
                ->label('Símbolo')
                ->required()
                ->maxLength(20)
                ->placeholder('USDT'),

            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(80)
                ->placeholder('Tether'),

            TextInput::make('network')
                ->label('Red')
                ->maxLength(40)
                ->placeholder('TRC-20'),

            TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->default(0),

            Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('symbol')->label('Símbolo')->badge()->color('info')->searchable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('network')->label('Red')->placeholder('—'),
                TextColumn::make('addresses_count')
                    ->label('Direcciones')
                    ->counts('addresses')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')->label('Activa')->boolean(),
                TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make()])
            ->bulkActions([]);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListDepositCurrencies::route('/'),
        ];
    }
}
