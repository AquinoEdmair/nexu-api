<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalLockResource\Pages\ManageWithdrawalLocks;
use App\Models\WithdrawalLock;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WithdrawalLockResource extends Resource
{
    protected static ?string $model = WithdrawalLock::class;

    protected static ?string $navigationIcon  = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'Candados de Retiros';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int    $navigationSort  = 4;
    protected static ?string $modelLabel      = 'Candado';
    protected static ?string $pluralModelLabel = 'Candados de Retiros';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('user_id')
                ->label('Usuario (Opcional)')
                ->relationship('user', 'email')
                ->searchable()
                ->helperText('Dejar vacío para que aplique a TODOS los usuarios (Configuración Global).')
                ->nullable(),

            Select::make('type')
                ->label('Tipo de Transacción')
                ->options([
                    'deposit'             => 'Depósito',
                    'yield'               => 'Rendimiento',
                    'referral_commission' => 'Comisión de Referido',
                    'commission'          => 'Comisión',
                ])
                ->required(),

            TextInput::make('days')
                ->label('Días de Bloqueo')
                ->numeric()
                ->minValue(0)
                ->default(30)
                ->required()
                ->helperText('Cantidad de días que los fondos estarán "En Activación".'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')
                    ->label('Usuario')
                    ->default('GLOBAL')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'GLOBAL' ? 'danger' : 'success')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'deposit'             => 'Depósito',
                        'yield'               => 'Rendimiento',
                        'referral_commission' => 'Comisión de Referido',
                        'commission'          => 'Comisión',
                        default               => $state,
                    }),

                TextColumn::make('days')
                    ->label('Días')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWithdrawalLocks::route('/'),
        ];
    }
}
