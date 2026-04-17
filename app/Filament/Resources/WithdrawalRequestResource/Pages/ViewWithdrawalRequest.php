<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithdrawalRequestResource\Pages;

use App\Filament\Resources\WithdrawalRequestResource;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewWithdrawalRequest extends ViewRecord
{
    protected static string $resource = WithdrawalRequestResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (WithdrawalRequest $record): bool => $record->status === 'pending')
                ->requiresConfirmation()
                ->modalHeading('Aprobar retiro')
                ->modalDescription('¿Confirmas la aprobación de este retiro? El usuario recibirá una notificación.')
                ->action(function (WithdrawalRequest $record, WithdrawalService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->approve($record, $admin);
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at']);
                    Notification::make()
                        ->title('Retiro aprobado correctamente.')
                        ->success()
                        ->send();
                }),

            Action::make('complete')
                ->label('Registrar tx_hash')
                ->color('info')
                ->icon('heroicon-o-check-badge')
                ->visible(fn (WithdrawalRequest $record): bool => $record->status === 'approved')
                ->form([
                    TextInput::make('tx_hash')
                        ->label('Hash de transacción')
                        ->required()
                        ->placeholder('0x...')
                        ->maxLength(255),
                ])
                ->action(function (WithdrawalRequest $record, array $data, WithdrawalService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->complete($record, $data['tx_hash'], $admin);
                    $this->refreshFormData(['status', 'tx_hash']);
                    Notification::make()
                        ->title('Retiro marcado como completado.')
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label('Rechazar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (WithdrawalRequest $record): bool => in_array($record->status, ['pending', 'approved'], strict: true))
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Motivo de rechazo')
                        ->required()
                        ->minLength(5)
                        ->maxLength(500)
                        ->rows(3),
                ])
                ->action(function (WithdrawalRequest $record, array $data, WithdrawalService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->reject($record, $data['rejection_reason'], $admin);
                    $this->refreshFormData(['status', 'rejection_reason', 'reviewed_by', 'reviewed_at']);
                    Notification::make()
                        ->title('Retiro rechazado. Fondos devueltos al usuario.')
                        ->warning()
                        ->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make('Solicitud de retiro')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        TextEntry::make('user.name')
                            ->label('Usuario'),

                        TextEntry::make('user.email')
                            ->label('Email'),

                        TextEntry::make('created_at')
                            ->label('Solicitado el')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('amount')
                            ->label('Monto')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('$'),

                        TextEntry::make('currency')
                            ->label('Moneda')
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('destination_address')
                            ->label('Dirección destino')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),

                Section::make('Estado')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => WithdrawalRequestResource::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => WithdrawalRequestResource::statusLabel($state)),

                        TextEntry::make('reviewer.name')
                            ->label('Revisado por')
                            ->placeholder('—'),

                        TextEntry::make('reviewed_at')
                            ->label('Revisado el')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('rejection_reason')
                            ->label(fn (WithdrawalRequest $record): string => $record->status === 'cancelled' ? 'Motivo de cancelación' : 'Motivo de rechazo')
                            ->placeholder('—')
                            ->color(fn (WithdrawalRequest $record): string => $record->status === 'cancelled' ? 'gray' : 'danger')
                            ->columnSpanFull()
                            ->visible(fn (WithdrawalRequest $record): bool => in_array($record->status, ['rejected', 'cancelled'], strict: true)),

                        TextEntry::make('tx_hash')
                            ->label('Hash de transacción')
                            ->placeholder('—')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull()
                            ->visible(fn (WithdrawalRequest $record): bool => $record->tx_hash !== null),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): WithdrawalRequest
    {
        /** @var WithdrawalRequest */
        return WithdrawalRequest::with(['user:id,name,email', 'reviewer:id,name'])->findOrFail($key);
    }
}
