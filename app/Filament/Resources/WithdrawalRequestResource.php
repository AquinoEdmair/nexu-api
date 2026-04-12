<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalRequestResource\Pages\ListWithdrawalRequests;
use App\Filament\Resources\WithdrawalRequestResource\Pages\ViewWithdrawalRequest;
use App\Models\WithdrawalRequest;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class WithdrawalRequestResource extends Resource
{
    protected static ?string $model = WithdrawalRequest::class;

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Retiros';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int    $navigationSort  = 4;

    protected static ?string $modelLabel = 'Solicitud de Retiro';
    protected static ?string $pluralModelLabel = 'Solicitudes de Retiro';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationBadge(): ?string
    {
        $count = WithdrawalRequest::pending()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
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

                TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn(string $state): string => TransactionResource::formatSmart($state))
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('destination_address')
                    ->label('Dirección destino')
                    ->limit(20)
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->sortable(),

                TextColumn::make('reviewer.name')
                    ->label('Revisado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewed_at')
                    ->label('Revisado el')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Solicitado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort(
                fn (Builder $query): Builder => $query->orderByRaw(
                    "CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END ASC"
                )->orderBy('created_at', 'asc')
            )
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'   => 'Pendiente',
                        'approved'  => 'Aprobado',
                        'completed' => 'Completado',
                        'rejected'  => 'Rechazado',
                    ]),

                SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options([
                        'USDT' => 'USDT',
                        'BTC'  => 'BTC',
                        'ETH'  => 'ETH',
                    ]),
            ])
            ->paginated([10, 25, 50])
            ->actions([])
            ->bulkActions([]);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawalRequests::route('/'),
            'view'  => ViewWithdrawalRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
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

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending'   => 'warning',
            'approved'  => 'info',
            'completed' => 'success',
            'rejected'  => 'danger',
            default     => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'pending'   => 'Pendiente',
            'approved'  => 'Aprobado',
            'completed' => 'Completado',
            'rejected'  => 'Rechazado',
            default     => $status,
        };
    }
}
