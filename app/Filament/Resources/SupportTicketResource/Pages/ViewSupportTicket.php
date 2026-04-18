<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

final class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    /** @return array<\Filament\Actions\Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Responder')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('primary')
                ->visible(fn (SupportTicket $record): bool => $record->isOpen())
                ->form([
                    Textarea::make('message')
                        ->label('Mensaje')
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000)
                        ->rows(5),
                ])
                ->action(function (SupportTicket $record, array $data, SupportTicketService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();

                    try {
                        $service->reply($record, $data['message'], 'admin', (string) $admin->id);
                        $this->refreshFormData(['status']);
                        Notification::make()->title('Respuesta enviada al usuario.')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('close')
                ->label('Cerrar ticket')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->visible(fn (SupportTicket $record): bool => $record->isOpen())
                ->requiresConfirmation()
                ->modalHeading('Cerrar ticket')
                ->modalDescription('El usuario recibirá un email indicando que su ticket ha sido resuelto.')
                ->action(function (SupportTicket $record, SupportTicketService $service): void {
                    /** @var \App\Models\Admin $admin */
                    $admin = auth()->user();

                    try {
                        $service->close($record, $admin);
                        $this->refreshFormData(['status', 'closed_at']);
                        Notification::make()->title('Ticket cerrado. Usuario notificado.')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->warning()->send();
                    }
                }),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->getRecord())
            ->schema([
                Section::make('Ticket de Soporte')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID')
                            ->formatStateUsing(fn (string $state): string => '#' . strtoupper(substr($state, 0, 8)))
                            ->copyable()
                            ->fontFamily('mono'),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (string $state): string => SupportTicketResource::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => SupportTicketResource::statusLabel($state)),

                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('user.name')
                            ->label('Usuario'),

                        TextEntry::make('user.email')
                            ->label('Email')
                            ->copyable(),

                        TextEntry::make('closed_at')
                            ->label('Cerrado el')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('subject')
                            ->label('Asunto')
                            ->columnSpanFull(),
                    ]),

                Section::make('Hilo de mensajes')
                    ->schema([
                        RepeatableEntry::make('messages')
                            ->label('')
                            ->schema([
                                TextEntry::make('sender_type')
                                    ->label('De')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'admin' ? 'primary' : 'gray')
                                    ->formatStateUsing(fn (string $state): string => $state === 'admin' ? 'Soporte' : 'Usuario'),

                                TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->dateTime('d/m/Y H:i:s'),

                                TextEntry::make('body')
                                    ->label('Mensaje')
                                    ->columnSpanFull()
                                    ->prose(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    protected function resolveRecord(int|string $key): SupportTicket
    {
        /** @var SupportTicket */
        return SupportTicket::with(['user:id,name,email', 'messages'])->findOrFail($key);
    }
}
