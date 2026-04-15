<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DepositInvoiceResource\Pages\ListDepositInvoices;
use App\Filament\Resources\DepositInvoiceResource\Pages\ViewDepositInvoice;
use App\Models\Admin;
use App\Models\DepositInvoice;
use App\Services\DepositService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class DepositInvoiceResource extends Resource
{
    protected static ?string $model = DepositInvoice::class;

    protected static ?string $navigationIcon  = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Historial de depósitos';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int    $navigationSort  = 3;
    protected static ?string $modelLabel      = 'Depósito';
    protected static ?string $pluralModelLabel = 'Historial de depósitos';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Usuario')
                ->schema([
                    TextEntry::make('user.name')->label('Nombre'),
                    TextEntry::make('user.email')->label('Email')->copyable(),
                ])
                ->columns(2),

            Section::make('Montos')
                ->schema([
                    TextEntry::make('amount_expected')
                        ->label('Monto USD (bruto)')
                        ->numeric(decimalPlaces: 2)
                        ->prefix('$'),

                    TextEntry::make('pay_amount')
                        ->label('Monto cripto a enviar')
                        ->formatStateUsing(fn(DepositInvoice $record): string =>
                            $record->pay_amount !== null
                                ? number_format((float) $record->pay_amount, 8, '.', '') . ' ' . strtoupper($record->currency)
                                : '—'
                        ),

                    TextEntry::make('amount_received')
                        ->label('Monto USD recibido')
                        ->numeric(decimalPlaces: 2)
                        ->prefix('$')
                        ->default('—'),
                ])
                ->columns(3),

            Section::make('Dirección de pago')
                ->schema([
                    TextEntry::make('address')
                        ->label('Dirección')
                        ->copyable()
                        ->fontFamily('mono'),

                    TextEntry::make('currency')->label('Moneda'),
                    TextEntry::make('network')->label('Red')->default('—'),
                    TextEntry::make('invoice_id')->label('Invoice ID')->copyable()->fontFamily('mono'),
                    TextEntry::make('tx_hash')->label('TX Hash')->copyable()->fontFamily('mono')->default('—'),
                ])
                ->columns(2),

            Section::make('Estado')
                ->schema([
                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->color(fn(string $state): string => self::statusColor($state))
                        ->formatStateUsing(fn(string $state): string => self::statusLabel($state)),

                    TextEntry::make('confirmed_by_admin')
                        ->label('Confirmado por')
                        ->state(function (DepositInvoice $r): string {
                            if (! str_starts_with((string) $r->tx_hash, 'manual-')) {
                                return 'Automático (webhook)';
                            }
                            preg_match('/^manual-(.+)-(\d+)$/', $r->tx_hash, $m);
                            $adminId = $m[1] ?? null;
                            return $adminId ? (Admin::find($adminId)?->name ?? "ID {$adminId}") : 'Manual';
                        }),

                    TextEntry::make('expires_at')
                        ->label('Expira')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('completed_at')
                        ->label('Completado')
                        ->dateTime('d/m/Y H:i')
                        ->default('—'),

                    TextEntry::make('created_at')
                        ->label('Creado')
                        ->dateTime('d/m/Y H:i'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->description(fn(DepositInvoice $r): string => $r->user?->email ?? '')
                    ->searchable(['users.name', 'users.email'])
                    ->sortable(),

                TextColumn::make('amount_expected')
                    ->label('USD solicitado')
                    ->formatStateUsing(fn(string $state): string => '$' . number_format((float) $state, 2))
                    ->sortable(),

                TextColumn::make('pay_amount')
                    ->label('Monto cripto')
                    ->formatStateUsing(fn(?string $state, DepositInvoice $record): string =>
                        $state !== null
                            ? number_format((float) $state, 8, '.', '') . ' ' . strtoupper($record->currency)
                            : '—'
                    )
                    ->fontFamily('mono'),

                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('network')
                    ->label('Red')
                    ->default('—')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(20)
                    ->copyable()
                    ->fontFamily('mono')
                    ->tooltip(fn(DepositInvoice $r): string => $r->address),

                TextColumn::make('confirmed_by')
                    ->label('Confirmado por')
                    ->state(function (DepositInvoice $r): string {
                        if (! str_starts_with((string) $r->tx_hash, 'manual-')) {
                            return 'Automático';
                        }
                        preg_match('/^manual-(.+)-(\d+)$/', $r->tx_hash, $m);
                        $adminId = $m[1] ?? null;
                        return $adminId ? (Admin::find($adminId)?->name ?? "ID {$adminId}") : 'Manual';
                    })
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Automático' ? 'gray' : 'warning'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn(string $state): string => self::statusLabel($state)),

                TextColumn::make('expires_at')
                    ->label('Expira')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'awaiting_payment' => 'Esperando pago',
                        'completed'        => 'Completado',
                        'expired'          => 'Expirado',
                        'failed'           => 'Fallido',
                    ]),

                SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options(fn() => DepositInvoice::distinct()->pluck('currency', 'currency')->sort()->toArray()),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('confirm')
                    ->label('Confirmar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(DepositInvoice $r): bool => $r->status === 'awaiting_payment')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar depósito manualmente')
                    ->modalDescription(fn(DepositInvoice $r): string =>
                        "Se acreditará $" . number_format((float) $r->amount_expected, 2) . " USD al balance de {$r->user?->name}. Esta acción queda registrada en el log de auditoría."
                    )
                    ->action(function (DepositInvoice $record): void {
                        try {
                            app(DepositService::class)->confirmManually($record, Auth::id());
                            Notification::make()
                                ->title('Depósito confirmado')
                                ->body("Se acreditó $" . number_format((float) $record->amount_expected, 2) . " USD al usuario {$record->user?->name}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al confirmar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(DepositInvoice $r): bool => $r->status === 'awaiting_payment')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar depósito')
                    ->modalDescription('El depósito se marcará como fallido. No se acreditará nada al usuario.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo de cancelación')
                            ->required()
                            ->minLength(5)
                            ->maxLength(500)
                            ->placeholder('Ej. Pago no recibido después de 48h, solicitado por el usuario, etc.'),
                    ])
                    ->action(function (DepositInvoice $record, array $data): void {
                        try {
                            app(DepositService::class)->cancelInvoice($record, Auth::id(), $data['reason']);
                            Notification::make()
                                ->title('Depósito cancelado')
                                ->body("Invoice {$record->invoice_id} marcado como fallido.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error al cancelar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListDepositInvoices::route('/'),
            'view'  => ViewDepositInvoice::route('/{record}'),
        ];
    }

    /** @return Builder<DepositInvoice> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user:id,name,email']);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'awaiting_payment' => 'warning',
            'completed'        => 'success',
            'expired'          => 'gray',
            'failed'           => 'danger',
            default            => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'awaiting_payment' => 'Esperando pago',
            'completed'        => 'Completado',
            'expired'          => 'Expirado',
            'failed'           => 'Fallido',
            default            => $status,
        };
    }
}
