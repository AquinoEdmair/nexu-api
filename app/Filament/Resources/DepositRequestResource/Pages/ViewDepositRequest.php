<?php

declare(strict_types=1);

namespace App\Filament\Resources\DepositRequestResource\Pages;

use App\Filament\Resources\DepositRequestResource;
use App\Models\DepositRequest;
use App\Services\DepositService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewDepositRequest extends ViewRecord
{
    protected static string $resource = DepositRequestResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar depósito')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (DepositRequest $record): bool => $record->status === 'client_confirmed')
                ->requiresConfirmation()
                ->modalHeading('Aprobar depósito')
                ->modalDescription('¿Confirmas la aprobación? Se acreditará el monto al usuario.')
                ->action(function (DepositRequest $record, DepositService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->approve($record, $admin);
                    $this->refreshFormData(['status', 'reviewed_by', 'reviewed_at']);
                    Notification::make()->title('Depósito aprobado y acreditado.')->success()->send();
                }),

            Action::make('cancel')
                ->label('Cancelar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (DepositRequest $record): bool => in_array($record->status, ['pending', 'client_confirmed'], strict: true))
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Motivo de cancelación')
                        ->rows(3)
                        ->maxLength(500),
                ])
                ->action(function (DepositRequest $record, array $data, DepositService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();
                    $service->cancel($record, $admin, $data['rejection_reason'] ?? null);
                    $this->refreshFormData(['status', 'rejection_reason', 'reviewed_by', 'reviewed_at']);
                    Notification::make()->title('Depósito cancelado.')->warning()->send();
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make('Solicitud de depósito')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        TextEntry::make('user.name')->label('Usuario'),
                        TextEntry::make('user.email')->label('Email'),
                        TextEntry::make('created_at')->label('Solicitado el')->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('currency')->label('Moneda')->badge()->color('info'),
                        TextEntry::make('network')->label('Red')->placeholder('—'),
                        TextEntry::make('amount_expected')->label('Monto esperado')->prefix('$')->numeric(decimalPlaces: 2),

                        TextEntry::make('address')
                            ->label('Dirección asignada')
                            ->copyable()
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        ImageEntry::make('qr_image_path')
                            ->label('QR de depósito')
                            ->disk('public')
                            ->height(200)
                            ->columnSpanFull()
                            ->visible(fn (DepositRequest $record): bool => $record->qr_image_path !== null),
                    ]),

                Section::make('Estado')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => DepositRequestResource::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => DepositRequestResource::statusLabel($state)),

                        TextEntry::make('client_confirmed_at')
                            ->label('Confirmado por cliente')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('tx_hash')
                            ->label('TX Hash del cliente')
                            ->copyable()
                            ->fontFamily('mono')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('reviewer.name')->label('Revisado por')->placeholder('—'),
                        TextEntry::make('reviewed_at')->label('Revisado el')->dateTime('d/m/Y H:i:s')->placeholder('—'),

                        TextEntry::make('rejection_reason')
                            ->label('Motivo cancelación')
                            ->placeholder('—')
                            ->color('danger')
                            ->columnSpanFull()
                            ->visible(fn (DepositRequest $record): bool => $record->status === 'cancelled'),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): DepositRequest
    {
        /** @var DepositRequest */
        return DepositRequest::with(['user:id,name,email', 'reviewer:id,name'])->findOrFail($key);
    }
}
