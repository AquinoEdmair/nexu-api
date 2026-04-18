<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SupportTicketResource\Pages;
use App\Models\SupportTicket;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Soporte';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $count = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->formatStateUsing(fn (string $state): string => '#' . strtoupper(substr($state, 0, 8)))
                    ->copyable()
                    ->fontFamily('mono'),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('subject')
                    ->label('Asunto')
                    ->searchable()
                    ->limit(60),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'open'        => 'warning',
                        'in_progress' => 'info',
                        'closed'      => 'gray',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'open'        => 'Abierto',
                        'in_progress' => 'En Progreso',
                        'closed'      => 'Cerrado',
                        default       => $state,
                    }),

                TextColumn::make('messages_count')
                    ->label('Mensajes')
                    ->counts('messages')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open'        => 'Abierto',
                        'in_progress' => 'En Progreso',
                        'closed'      => 'Cerrado',
                    ]),
            ])
            ->actions([
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->url(fn (SupportTicket $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view'  => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match($status) {
            'open'        => 'Abierto',
            'in_progress' => 'En Progreso',
            'closed'      => 'Cerrado',
            default       => $status,
        };
    }

    public static function statusColor(string $status): string
    {
        return match($status) {
            'open'        => 'warning',
            'in_progress' => 'info',
            'closed'      => 'gray',
            default       => 'gray',
        };
    }
}
