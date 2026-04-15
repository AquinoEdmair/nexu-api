<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Admin;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

final class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make('Datos principales')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID de transacción')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn(string $state): string => TransactionResource::typeColor($state))
                            ->formatStateUsing(fn(string $state): string => TransactionResource::typeLabel($state)),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn(string $state): string => TransactionResource::statusColor($state))
                            ->formatStateUsing(fn(string $state): string => TransactionResource::statusLabel($state)),

                        TextEntry::make('currency')
                            ->label('Moneda')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('amount')
                            ->label('Monto bruto')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$'),

                        TextEntry::make('fee_amount')
                            ->label('Comisión')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$')
                            ->color('danger'),

                        TextEntry::make('net_amount')
                            ->label('Monto neto')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$')
                            ->weight('bold'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpan(2),

                        TextEntry::make('created_at')
                            ->label('Creada el')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('updated_at')
                            ->label('Actualizada el')
                            ->dateTime('d/m/Y H:i:s'),
                    ]),

                Section::make('Trazabilidad')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('external_tx_id')
                            ->label('TX externo (proveedor cripto)')
                            ->copyable()
                            ->fontFamily('mono')
                            ->placeholder('N/A')
                            ->columnSpanFull(),

                        TextEntry::make('reference_type')
                            ->label('Tipo de referencia')
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                'yield_log'           => 'Aplicación de rendimiento',
                                'withdrawal_request'  => 'Solicitud de retiro',
                                'referral'            => 'Referido',
                                null                  => '—',
                                default               => $state,
                            })
                            ->placeholder('—'),

                        TextEntry::make('reference_id')
                            ->label('ID de referencia')
                            ->copyable()
                            ->fontFamily('mono')
                            ->placeholder('—'),
                    ]),

                Section::make('Usuario')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Nombre'),

                        TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),

                        TextEntry::make('user.phone')
                            ->label('Teléfono')
                            ->placeholder('—'),

                        TextEntry::make('user.status')
                            ->label('Estado del usuario')
                            ->badge()
                            ->color(fn(?string $state): string => match ($state) {
                                'active'  => 'success',
                                'blocked' => 'danger',
                                'pending' => 'warning',
                                default   => 'gray',
                            })
                            ->formatStateUsing(fn(?string $state): string => match ($state) {
                                'active'  => 'Activo',
                                'blocked' => 'Bloqueado',
                                'pending' => 'Pendiente',
                                default   => '—',
                            }),
                    ]),

                Section::make('Admin responsable')
                    ->columns(2)
                    ->collapsed()
                    ->visible(fn(Transaction $record): bool =>
                        $record->type === 'admin_adjustment' ||
                        ($record->type === 'deposit' && data_get($record->metadata, 'confirmed_by') !== null)
                    )
                    ->schema([
                        TextEntry::make('admin_name')
                            ->label('Nombre del admin')
                            ->state(function (Transaction $record): string {
                                $adminId = $record->type === 'admin_adjustment'
                                    ? data_get($record->metadata, 'admin_id')
                                    : data_get($record->metadata, 'confirmed_by');
                                if (! $adminId) {
                                    return '—';
                                }
                                $admin = Admin::find($adminId);
                                return $admin?->name ?? "ID {$adminId}";
                            }),

                        TextEntry::make('admin_email')
                            ->label('Email del admin')
                            ->state(function (Transaction $record): string {
                                $adminId = $record->type === 'admin_adjustment'
                                    ? data_get($record->metadata, 'admin_id')
                                    : data_get($record->metadata, 'confirmed_by');
                                if (! $adminId) {
                                    return '—';
                                }
                                $admin = Admin::find($adminId);
                                return $admin?->email ?? '—';
                            })
                            ->copyable(),
                    ]),

                Section::make('Wallet al momento de la operación')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('wallet.balance_in_operation')
                            ->label('En operación')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$')
                            ->placeholder('—'),

                        TextEntry::make('wallet.balance_total')
                            ->label('Total')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$')
                            ->weight('bold')
                            ->placeholder('—'),
                    ]),

                Section::make('Metadata')
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): Transaction
    {
        /** @var Transaction */
        return Transaction::with([
            'user:id,name,email,status,phone',
            'wallet:id,user_id,balance_in_operation,balance_total',
        ])->findOrFail($key);
    }
}
