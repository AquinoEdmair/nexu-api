<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalCurrencyResource\Pages;
use App\Models\WithdrawalCurrency;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WithdrawalCurrencyResource extends Resource
{
    protected static ?string $model = WithdrawalCurrency::class;

    protected static ?string $navigationIcon   = 'heroicon-o-arrow-up-circle';
    protected static ?string $navigationLabel  = 'Monedas de retiro';
    protected static ?string $navigationGroup  = 'Configuración';
    protected static ?int    $navigationSort   = 4;
    protected static ?string $modelLabel       = 'Moneda de retiro';
    protected static ?string $pluralModelLabel = 'Monedas de retiro';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos de la moneda')
                ->columns(2)
                ->schema([
                    TextInput::make('symbol')
                        ->label('Símbolo')
                        ->required()
                        ->unique(WithdrawalCurrency::class, 'symbol', ignoreRecord: true)
                        ->maxLength(20)
                        ->placeholder('USDT')
                        ->helperText('Mayúsculas. Ej. BTC, ETH, USDT.'),

                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(80)
                        ->placeholder('Tether (TRC20)'),

                    TextInput::make('network')
                        ->label('Red')
                        ->nullable()
                        ->maxLength(40)
                        ->placeholder('TRC20')
                        ->helperText('Nombre visible de la red. Ej. TRC20, ERC20, Bitcoin.'),

                    TextInput::make('sort_order')
                        ->label('Orden de aparición')
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Menor número aparece primero.'),

                    Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->helperText('Solo las monedas activas aparecen en el formulario de retiro.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                TextColumn::make('symbol')
                    ->label('Símbolo')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable(),

                TextColumn::make('network')
                    ->label('Red')
                    ->default('—')
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWithdrawalCurrencies::route('/'),
            'create' => Pages\CreateWithdrawalCurrency::route('/create'),
            'edit'   => Pages\EditWithdrawalCurrency::route('/{record}/edit'),
        ];
    }
}
