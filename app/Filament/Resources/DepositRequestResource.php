<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DepositRequestResource\Pages\ListDepositRequests;
use App\Filament\Resources\DepositRequestResource\Pages\ViewDepositRequest;
use App\Models\DepositRequest;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class DepositRequestResource extends Resource
{
    protected static ?string $model = DepositRequest::class;

    protected static ?string $navigationIcon   = 'heroicon-o-arrow-down-circle';
    protected static ?string $navigationLabel  = 'Solicitudes depósito';
    protected static ?string $navigationGroup  = 'Depósitos';
    protected static ?int    $navigationSort   = 3;
    protected static ?string $modelLabel       = 'Solicitud';
    protected static ?string $pluralModelLabel = 'Solicitudes de depósito';

    public static function getNavigationBadge(): ?string
    {
        $count = DepositRequest::where('status', 'client_confirmed')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('info'),

                TextColumn::make('amount_expected')
                    ->label('Monto')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state)),

                TextColumn::make('tx_hash')
                    ->label('TX Hash')
                    ->copyable()
                    ->fontFamily('mono')
                    ->limit(16)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewer.name')
                    ->label('Revisado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort(function ($query) {
                // client_confirmed first, then pending, then others
                $query->orderByRaw("CASE status
                    WHEN 'client_confirmed' THEN 0
                    WHEN 'pending' THEN 1
                    ELSE 2
                END")
                ->orderBy('created_at');
            })
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'          => 'Pendiente',
                        'client_confirmed' => 'Confirmado cliente',
                        'completed'        => 'Completado',
                        'cancelled'        => 'Cancelado',
                    ]),

                SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options(
                        DepositRequest::distinct()->pluck('currency', 'currency')->toArray()
                    ),
            ])
            ->actions([\Filament\Tables\Actions\ViewAction::make()])
            ->bulkActions([]);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListDepositRequests::route('/'),
            'view'  => ViewDepositRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool  { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending'          => 'warning',
            'client_confirmed' => 'info',
            'completed'        => 'success',
            'cancelled'        => 'danger',
            default            => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'          => 'Pendiente',
            'client_confirmed' => 'Confirmado cliente',
            'completed'        => 'Completado',
            'cancelled'        => 'Cancelado',
            default            => $status,
        };
    }
}
