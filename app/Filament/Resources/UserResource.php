<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Gate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Usuarios';
    protected static ?int    $navigationSort  = 1;

    protected static ?string $recordTitleAttribute = 'email';

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'referral_code'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Datos personales')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre completo')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->maxLength(150)
                        ->visibleOn('create'),

                    TextInput::make('email')
                        ->label('Email')
                        ->disabled()
                        ->visibleOn('edit'),

                    TextInput::make('phone')
                        ->label('Teléfono')
                        ->tel()
                        ->nullable()
                        ->maxLength(20),

                    TextInput::make('referred_by_code')
                        ->label('Código del referidor')
                        ->helperText('Opcional — código de referido de quien invitó al usuario.')
                        ->nullable()
                        ->maxLength(10)
                        ->visibleOn('create'),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Datos personales')
                ->schema([
                    TextEntry::make('id')
                        ->label('ID')
                        ->copyable(),

                    TextEntry::make('name')
                        ->label('Nombre'),

                    TextEntry::make('email')
                        ->label('Email')
                        ->copyable(),

                    TextEntry::make('phone')
                        ->label('Teléfono')
                        ->default('Sin teléfono'),

                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn(string $state): string => match ($state) {
                            'active'  => 'success',
                            'blocked' => 'danger',
                            'pending' => 'gray',
                            default   => 'gray',
                        }),

                    TextEntry::make('referral_code')
                        ->label('Código de referido')
                        ->copyable(),

                    TextEntry::make('created_at')
                        ->label('Registro')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('email_verified_at')
                        ->label('Email verificado')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('No verificado'),
                ])
                ->columns(2),

            InfolistSection::make('Wallet')
                ->schema([
                    TextEntry::make('wallet.balance_available')
                        ->label('Balance disponible')
                        ->numeric(decimalPlaces: 8)
                        ->prefix('$'),

                    TextEntry::make('wallet.balance_in_operation')
                        ->label('En operación')
                        ->numeric(decimalPlaces: 8)
                        ->prefix('$'),

                    TextEntry::make('wallet.balance_total')
                        ->label('Balance total')
                        ->numeric(decimalPlaces: 8)
                        ->prefix('$')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold),
                ])
                ->columns(3),

            InfolistSection::make('Referido por')
                ->schema([
                    TextEntry::make('referrer.name')
                        ->label('Nombre')
                        ->default('Registro orgánico'),

                    TextEntry::make('referrer.email')
                        ->label('Email')
                        ->default('—'),
                ])
                ->columns(2),

            InfolistSection::make('Bloqueo')
                ->schema([
                    TextEntry::make('blocked_reason')
                        ->label('Motivo del bloqueo')
                        ->default('—'),

                    TextEntry::make('blocked_at')
                        ->label('Fecha de bloqueo')
                        ->dateTime('d/m/Y H:i')
                        ->default('—'),
                ])
                ->columns(2)
                ->visible(fn(User $record): bool => $record->status === 'blocked'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active'  => 'success',
                        'blocked' => 'danger',
                        'pending' => 'gray',
                        default   => 'gray',
                    }),

                TextColumn::make('wallet.balance_total')
                    ->label('Balance total')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable()
                    ->default('0.00'),

                TextColumn::make('referrals_count')
                    ->label('Referidos')
                    ->counts('referrals')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registro')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'active'  => 'Activo',
                        'blocked' => 'Bloqueado',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('block')
                    ->label('Bloquear')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn(User $record): bool => Gate::allows('block', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo del bloqueo')
                            ->required()
                            ->minLength(10)
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        /** @var \App\Models\Admin $admin */
                        $admin = auth()->user();
                        app(UserService::class)->updateStatus($record, 'blocked', $data['reason'], $admin);
                    }),

                Action::make('unblock')
                    ->label('Desbloquear')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(User $record): bool => Gate::allows('unblock', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo de reactivación')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        /** @var \App\Models\Admin $admin */
                        $admin = auth()->user();
                        app(UserService::class)->updateStatus($record, 'active', $data['reason'], $admin);
                    }),

                Action::make('resetPassword')
                    ->label('Resetear contraseña')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->visible(fn(User $record): bool => Gate::allows('resetPassword', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Resetear contraseña')
                    ->modalDescription('Se generará una contraseña temporal y se enviará al correo del usuario. ¿Confirmar?')
                    ->action(function (User $record): void {
                        /** @var \App\Models\Admin $admin */
                        $admin = auth()->user();
                        app(UserService::class)->resetPassword($record, $admin);
                    }),
            ])
            ->bulkActions([]);
    }

    /** @return array<class-string> */
    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\ReferralsRelationManager::class,
            RelationManagers\ElitePointsRelationManager::class,
        ];
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view'   => Pages\ViewUser::route('/{record}'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
