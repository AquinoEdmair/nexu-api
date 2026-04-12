<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionConfigResource\Pages\CreateCommissionConfig;
use App\Filament\Resources\CommissionConfigResource\Pages\ListCommissionConfigs;
use App\Filament\Resources\CommissionConfigResource\Pages\ViewCommissionConfig;
use App\Models\CommissionConfig;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class CommissionConfigResource extends Resource
{
    protected static ?string $model = CommissionConfig::class;

    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Comisiones';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int    $navigationSort  = 1;

    protected static ?string $modelLabel = 'Configuración de Comisión';
    protected static ?string $pluralModelLabel = 'Configuraciones de Comisiones';

    protected static ?string $recordTitleAttribute = 'type';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'deposit' ? 'info' : 'warning')
                    ->formatStateUsing(fn (string $state): string => self::typeLabel($state)),

                TextColumn::make('value')
                    ->label('Valor')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(40)
                    ->placeholder('—'),

                TextColumn::make('createdBy.name')
                    ->label('Creada por')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort(
                fn (Builder $query): Builder => $query->orderByRaw('is_active DESC, created_at DESC')
            )
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'deposit'    => 'Depósito',
                        'withdrawal' => 'Retiro',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas')
                    ->placeholder('Todas'),
            ])
            ->paginated([25, 50])
            ->actions([])
            ->bulkActions([]);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index'  => ListCommissionConfigs::route('/'),
            'create' => CreateCommissionConfig::route('/create'),
            'view'   => ViewCommissionConfig::route('/{record}'),
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

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'deposit'    => 'Depósito',
            'withdrawal' => 'Retiro',
            default      => $type,
        };
    }

    public static function typeColor(string $type): string
    {
        return match ($type) {
            'deposit'    => 'info',
            'withdrawal' => 'warning',
            default      => 'gray',
        };
    }
}
