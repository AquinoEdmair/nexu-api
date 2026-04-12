<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Models\Admin;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Gate;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminResource extends Resource
{
    protected static ?string $model = Admin::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int    $navigationSort  = 10;

    protected static ?string $modelLabel = 'Administrador';
    protected static ?string $pluralModelLabel = 'Administradores';

    protected static ?string $recordTitleAttribute = 'email';

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return $record instanceof Admin && Gate::allows('update', $record);
    }

    public static function form(Form $form): Form
    {
        // Form schema is defined in individual page classes (CreateAdmin, EditAdmin)
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // Infolist schema is defined in ViewAdmin page
        return $infolist->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => 'Super Admin',
                        'manager'     => 'Manager',
                        default       => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'manager'     => 'info',
                        default       => 'gray',
                    }),

                IconColumn::make('two_factor_confirmed_at')
                    ->label('2FA')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-shield-exclamation')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Admin $record): bool => $record->two_factor_confirmed_at !== null),

                TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->since()
                    ->placeholder('Nunca'),

                TextColumn::make('last_login_ip')
                    ->label('Última IP')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'manager'     => 'Manager',
                    ]),

                Filter::make('has_2fa')
                    ->label('2FA activo')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('two_factor_confirmed_at')),

                Filter::make('no_2fa')
                    ->label('Sin 2FA')
                    ->query(fn (Builder $query): Builder => $query->whereNull('two_factor_confirmed_at')),
            ])
            ->bulkActions([]);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'view'   => Pages\ViewAdmin::route('/{record}'),
            'edit'   => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
