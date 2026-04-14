<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\EliteTier;
use App\Models\User;
use App\Services\AdminAdjustmentService;
use App\Services\EliteTierService;
use App\Services\UserService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
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

    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';

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

            InfolistSection::make('Nivel Élite')
                ->schema([
                    TextEntry::make('eliteTier.name')
                        ->label('Tier actual')
                        ->badge()
                        ->default('Sin asignar'),

                    TextEntry::make('elite_points_total')
                        ->label('Puntos acumulados')
                        ->state(fn(User $record): string => number_format(
                            (float) $record->elitePoints()->sum('points'),
                            2, '.', ''
                        ))
                        ->default('0'),

                    IconEntry::make('elite_tier_manual_override')
                        ->label('Override manual')
                        ->boolean()
                        ->trueIcon('heroicon-o-lock-closed')
                        ->falseIcon('heroicon-o-lock-open')
                        ->trueColor('warning')
                        ->falseColor('gray'),
                ])
                ->columns(3),

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

                TextColumn::make('eliteTier.name')
                    ->label('Tier')
                    ->badge()
                    ->default('—')
                    ->sortable(),

                TextColumn::make('elite_points_sum_points')
                    ->label('Puntos')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->default('0'),

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
                Action::make('assignEliteTier')
                    ->label('Asignar tier')
                    ->icon('heroicon-o-trophy')
                    ->color('warning')
                    ->form([
                        Select::make('elite_tier_id')
                            ->label('Nivel Élite')
                            ->options(
                                EliteTier::query()
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->nullable()
                            ->placeholder('Sin asignar'),

                        Toggle::make('elite_tier_manual_override')
                            ->label('Override manual (evita recálculo automático)')
                            ->helperText('Activo = el tier no cambia aunque cambien sus puntos.'),
                    ])
                    ->fillForm(fn(User $record): array => [
                        'elite_tier_id'              => $record->elite_tier_id,
                        'elite_tier_manual_override' => $record->elite_tier_manual_override,
                    ])
                    ->action(function (User $record, array $data): void {
                        $service = app(EliteTierService::class);

                        if ($data['elite_tier_id'] !== null) {
                            $tier = EliteTier::findOrFail($data['elite_tier_id']);
                            $service->assignManually($record, $tier);

                            if (! $data['elite_tier_manual_override']) {
                                $service->releaseOverride($record);
                            }
                        } else {
                            $service->releaseOverride($record);
                            $record->update(['elite_tier_id' => null]);
                        }

                        Notification::make()
                            ->title('Tier actualizado')
                            ->success()
                            ->send();
                    }),

                Action::make('recalculateEliteTier')
                    ->label('Recalcular tier')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Recalcular nivel Élite')
                    ->modalDescription('Se recalculará el tier según los puntos actuales del usuario y se liberará el override manual si estaba activo.')
                    ->action(function (User $record): void {
                        app(EliteTierService::class)->releaseOverride($record);
                        app(EliteTierService::class)->recalculateForUser($record->fresh());

                        Notification::make()
                            ->title('Tier recalculado')
                            ->success()
                            ->send();
                    }),

                Action::make('adjustBalance')
                    ->label('Ajustar balance')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->form([
                        Select::make('field')
                            ->label('Campo')
                            ->options([
                                'balance_available'    => 'Balance disponible',
                                'balance_in_operation' => 'En operación',
                            ])
                            ->required(),

                        Select::make('direction')
                            ->label('Tipo')
                            ->options([
                                'credit' => 'Crédito (sumar)',
                                'debit'  => 'Débito (restar)',
                            ])
                            ->required(),

                        TextInput::make('amount')
                            ->label('Monto (USD)')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->extraInputAttributes(['step' => 'any'])
                            ->placeholder('100'),

                        Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        $delta = $data['direction'] === 'credit'
                            ? number_format((float) $data['amount'], 8, '.', '')
                            : '-' . number_format((float) $data['amount'], 8, '.', '');

                        /** @var \App\Models\Admin $admin */
                        $admin = auth()->user();

                        try {
                            app(AdminAdjustmentService::class)->adjustWallet(
                                $record,
                                $data['field'],
                                $delta,
                                $data['reason'],
                                (string) $admin->id,
                            );

                            Notification::make()
                                ->title('Balance ajustado')
                                ->success()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('No se puede aplicar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('adjustPoints')
                    ->label('Ajustar puntos')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->form([
                        Select::make('direction')
                            ->label('Tipo')
                            ->options([
                                'award'  => 'Agregar puntos',
                                'deduct' => 'Quitar puntos',
                            ])
                            ->required(),

                        TextInput::make('points')
                            ->label('Puntos')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->extraInputAttributes(['step' => 'any'])
                            ->placeholder('100'),

                        Textarea::make('reason')
                            ->label('Motivo')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        $delta = $data['direction'] === 'award'
                            ? number_format((float) $data['points'], 2, '.', '')
                            : '-' . number_format((float) $data['points'], 2, '.', '');

                        /** @var \App\Models\Admin $admin */
                        $admin = auth()->user();

                        app(AdminAdjustmentService::class)->adjustPoints(
                            $record,
                            $delta,
                            $data['reason'],
                            (string) $admin->id,
                        );

                        Notification::make()
                            ->title('Puntos ajustados')
                            ->success()
                            ->send();
                    }),

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

    /** @return \Illuminate\Database\Eloquent\Builder<User> */
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withSum('elitePoints', 'points');
    }

    /** @return array<class-string> */
    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
            RelationManagers\AdminAdjustmentsRelationManager::class,
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
