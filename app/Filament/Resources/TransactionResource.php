<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Models\Admin;
use App\Models\Transaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon  = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Transacciones';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $modelLabel      = 'Transacción';
    protected static ?string $pluralModelLabel = 'Transacciones';
    protected static ?string $recordTitleAttribute = 'id';

    // Read-only resource — no create/edit forms needed
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
                    ->description(fn(Transaction $r): string => $r->user?->email ?? '')
                    ->searchable(['users.name', 'users.email'])
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => self::typeColor($state))
                    ->formatStateUsing(fn(string $state): string => self::typeLabel($state)),

                TextColumn::make('amount')
                    ->label('Bruto')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->sortable(),

                TextColumn::make('fee_amount')
                    ->label('Comisión')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->color('danger'),

                TextColumn::make('net_amount')
                    ->label('Neto')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn(string $state): string => self::statusLabel($state)),

                TextColumn::make('external_tx_id')
                    ->label('TX externo')
                    ->limit(12)
                    ->copyable()
                    ->placeholder('—')
                    ->tooltip(fn(?string $state): ?string => $state),

                TextColumn::make('admin_actor')
                    ->label('Admin')
                    ->state(function (Transaction $record): string {
                        // Yield: applied_by on the YieldLog (eager loaded)
                        if ($record->type === 'yield') {
                            return $record->yieldLog?->appliedBy?->name ?? '—';
                        }
                        // Withdrawal: reviewer on the WithdrawalRequest (eager loaded)
                        if ($record->type === 'withdrawal') {
                            return $record->withdrawalRequest?->reviewer?->name ?? '—';
                        }
                        // Admin adjustment: metadata.admin_id
                        if ($record->type === 'admin_adjustment') {
                            $adminId = data_get($record->metadata, 'admin_id');
                            if ($adminId) {
                                return Admin::find($adminId)?->name ?? "ID {$adminId}";
                            }
                        }
                        // Manually confirmed deposit
                        if ($record->type === 'deposit') {
                            $adminId = data_get($record->metadata, 'confirmed_by');
                            if (! $adminId && str_starts_with((string) $record->external_tx_id, 'manual-')) {
                                preg_match('/^manual-(.+)-(\d+)$/', $record->external_tx_id, $m);
                                $adminId = $m[1] ?? null;
                            }
                            if ($adminId) {
                                return Admin::find($adminId)?->name ?? "ID {$adminId}";
                            }
                        }
                        return '—';
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('user')
                    ->label('Usuario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'deposit'             => 'Depósito',
                        'withdrawal'          => 'Retiro',
                        'commission'          => 'Comisión',
                        'yield'               => 'Rendimiento',
                        'referral_commission' => 'Com. Referido',
                        'investment'          => 'Inversión',
                    ])
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'confirmed'  => 'Confirmado',
                        'pending'    => 'Pendiente',
                        'processing' => 'Procesando',
                        'rejected'   => 'Rechazado',
                    ])
                    ->multiple(),

                SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options(fn(): array => Cache::remember(
                        'transactions:currencies',
                        now()->addMinutes(5),
                        fn(): array => Transaction::query()
                            ->distinct()
                            ->orderBy('currency')
                            ->pluck('currency', 'currency')
                            ->toArray(),
                    )),

                Filter::make('date_range')
                    ->label('Rango de fecha')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date_from')->label('Desde'),
                        \Filament\Forms\Components\DatePicker::make('date_to')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['date_from'], fn(Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['date_to'],   fn(Builder $q, string $d) => $q->whereDate('created_at', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['date_from'] ?? null) {
                            $indicators[] = 'Desde: ' . $data['date_from'];
                        }
                        if ($data['date_to'] ?? null) {
                            $indicators[] = 'Hasta: ' . $data['date_to'];
                        }
                        return $indicators;
                    }),

                Filter::make('amount_range')
                    ->label('Monto neto')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('amount_min')->label('Mínimo')->numeric(),
                        \Filament\Forms\Components\TextInput::make('amount_max')->label('Máximo')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['amount_min'], fn(Builder $q, string $v) => $q->where('net_amount', '>=', (float) $v))
                            ->when($data['amount_max'], fn(Builder $q, string $v) => $q->where('net_amount', '<=', (float) $v));
                    }),
            ])
            ->filtersLayout(\Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->paginated([25, 50, 100])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    /** @return array<string> */
    public static function getRelations(): array
    {
        return [];
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'view'  => ViewTransaction::route('/{record}'),
        ];
    }

    /** @param Builder<Transaction> $query */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user:id,name,email,status',
                'yieldLog.appliedBy:id,name',
                'withdrawalRequest.reviewer:id,name',
            ]);
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

    // ── Shared presentation helpers ───────────────────────────────────────────
    // Used by TransactionResource::table(), ViewTransaction::infolist(),
    // and TransactionsRelationManager::table() to avoid duplicating match expressions.

    public static function typeColor(string $type): string
    {
        return match ($type) {
            'deposit'             => 'info',
            'withdrawal'          => 'warning',
            'commission'          => 'gray',
            'yield'               => 'success',
            'referral_commission' => 'purple',
            'investment'          => 'primary',
            'admin_adjustment'    => 'danger',
            default               => 'gray',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'deposit'             => 'Depósito',
            'withdrawal'          => 'Retiro',
            'commission'          => 'Comisión',
            'yield'               => 'Rendimiento',
            'referral_commission' => 'Com. Referido',
            'investment'          => 'Inversión',
            'admin_adjustment'    => 'Ajuste admin',
            default               => $type,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'confirmed'  => 'success',
            'pending'    => 'warning',
            'processing' => 'info',
            'rejected'   => 'danger',
            default      => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'confirmed'  => 'Confirmado',
            'pending'    => 'Pendiente',
            'processing' => 'Procesando',
            'rejected'   => 'Rechazado',
            default      => $status,
        };
    }

    public static function formatSmart(string|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        $val = (float) $value;

        // If it's effectively an integer or has at most 2 significant decimals, 
        // show 2 decimals (standard currency format).
        if ($val == round($val, 2)) {
            return number_format($val, 2, '.', ',');
        }

        // Otherwise, show up to 8 decimals but strip trailing zeros.
        return rtrim(rtrim(number_format($val, 8, '.', ','), '0'), '.');
    }
}
