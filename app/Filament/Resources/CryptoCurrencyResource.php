<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CryptoCurrencyResource\Pages\EditCryptoCurrency;
use App\Filament\Resources\CryptoCurrencyResource\Pages\ListCryptoCurrencies;
use App\Models\CryptoCurrency;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class CryptoCurrencyResource extends Resource
{
    protected static ?string $model = CryptoCurrency::class;

    protected static ?string $navigationIcon  = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Monedas cripto';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $modelLabel      = 'Moneda';
    protected static ?string $pluralModelLabel = 'Monedas cripto';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos de la moneda')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(80)
                        ->placeholder('Ej. Bitcoin'),

                    TextInput::make('symbol')
                        ->label('Símbolo')
                        ->required()
                        ->unique(CryptoCurrency::class, 'symbol', ignoreRecord: true)
                        ->maxLength(20)
                        ->placeholder('BTC')
                        ->helperText('Mayúsculas. Ej. BTC, ETH, USDT.'),

                    TextInput::make('now_payments_code')
                        ->label('Código NowPayments')
                        ->required()
                        ->maxLength(40)
                        ->placeholder('btc')
                        ->helperText('Valor exacto que acepta NowPayments en pay_currency. Ej. btc, eth, usdttrc20.'),

                    TextInput::make('network')
                        ->label('Red')
                        ->nullable()
                        ->maxLength(40)
                        ->placeholder('Bitcoin')
                        ->helperText('Nombre visible de la red. Ej. Bitcoin, TRC20, ERC20.'),

                    TextInput::make('sort_order')
                        ->label('Orden de aparición')
                        ->integer()
                        ->minValue(0)
                        ->default(0),

                    Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true)
                        ->helperText('Solo las monedas activas aparecen en el frontend y se aceptan en depósitos.'),
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

                TextColumn::make('now_payments_code')
                    ->label('Código NowPayments')
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('network')
                    ->label('Red')
                    ->default('—')
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListCryptoCurrencies::route('/'),
            'edit'  => EditCryptoCurrency::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
}
