<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DepositAddressResource\Pages\ListDepositAddresses;
use App\Models\DepositAddress;
use App\Models\DepositCurrency;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class DepositAddressResource extends Resource
{
    protected static ?string $model = DepositAddress::class;

    protected static ?string $navigationIcon   = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel  = 'Direcciones depósito';
    protected static ?string $navigationGroup  = 'Depósitos';
    protected static ?int    $navigationSort   = 2;
    protected static ?string $modelLabel       = 'Dirección';
    protected static ?string $pluralModelLabel = 'Direcciones de depósito';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('currency_id')
                ->label('Moneda')
                ->options(DepositCurrency::where('is_active', true)->orderBy('sort_order')->pluck('symbol', 'id'))
                ->required()
                ->searchable(),

            TextInput::make('address')
                ->label('Dirección')
                ->required()
                ->maxLength(255)
                ->placeholder('TYourCryptoAddress...'),

            TextInput::make('label')
                ->label('Etiqueta')
                ->maxLength(80)
                ->placeholder('Billetera principal'),

            FileUpload::make('qr_image_path')
                ->label('Imagen QR')
                ->image()
                ->disk('public')
                ->directory('deposit-qr')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(5120)
                ->imagePreviewHeight('200'),

            Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('qr_image_path')
                    ->label('QR')
                    ->disk('public')
                    ->height(48)
                    ->width(48),

                TextColumn::make('currency.symbol')
                    ->label('Moneda')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('address')
                    ->label('Dirección')
                    ->copyable()
                    ->fontFamily('mono')
                    ->limit(30),

                TextColumn::make('label')
                    ->label('Etiqueta')
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('currency_id')
                    ->label('Moneda')
                    ->options(DepositCurrency::orderBy('sort_order')->pluck('symbol', 'id')),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make()])
            ->bulkActions([]);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListDepositAddresses::route('/'),
        ];
    }
}
