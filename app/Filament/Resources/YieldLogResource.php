<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\YieldLogResource\Pages\CreateYieldLog;
use App\Filament\Resources\YieldLogResource\Pages\ListYieldLogs;
use App\Filament\Resources\YieldLogResource\Pages\ViewYieldLog;
use App\Filament\Resources\YieldLogResource\RelationManagers\YieldLogUsersRelationManager;
use App\Models\YieldLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class YieldLogResource extends Resource
{
    protected static ?string $model = YieldLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'Rendimientos';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Rendimiento';
    protected static ?string $pluralModelLabel = 'Rendimientos';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('appliedBy.name')
                    ->label('Admin')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => self::typeColor($state))
                    ->formatStateUsing(fn(string $state): string => self::typeLabel($state)),

                TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(
                        fn(string $state, YieldLog $record): string => $record->type === 'percentage'
                        ? number_format((float) $state, 4) . '%'
                        : '$' . number_format((float) $state, 2)
                    ),

                TextColumn::make('scope')
                    ->label('Alcance')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'all' ? 'gray' : 'warning')
                    ->formatStateUsing(fn(string $state): string => $state === 'all' ? 'Todos' : 'Específico'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn(string $state): string => self::statusLabel($state)),

                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_applied')
                    ->label('Total aplicado')
                    ->formatStateUsing(fn(string $state): string => TransactionResource::formatSmart($state))
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('applied_at')
                    ->label('Aplicado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Completado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente')
                    ->sortable(),
            ])
            ->defaultSort('applied_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'processing' => 'Procesando',
                        'completed' => 'Completado',
                        'failed' => 'Fallido',
                    ]),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'percentage' => 'Porcentaje',
                        'fixed_amount' => 'Monto fijo',
                    ]),

                SelectFilter::make('scope')
                    ->label('Alcance')
                    ->options([
                        'all' => 'Todos los usuarios',
                        'specific_user' => 'Usuario específico',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            YieldLogUsersRelationManager::class,
        ];
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListYieldLogs::route('/'),
            'create' => CreateYieldLog::route('/create'),
            'view' => ViewYieldLog::route('/{record}'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    // ── Static presentation helpers ──────────────────────────────────────────

    public static function typeColor(string $type): string
    {
        return match ($type) {
            'percentage' => 'info',
            'fixed_amount' => 'success',
            default => 'gray',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'percentage' => 'Porcentaje',
            'fixed_amount' => 'Monto fijo',
            default => $type,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'processing' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            default => $status,
        };
    }
}
}
