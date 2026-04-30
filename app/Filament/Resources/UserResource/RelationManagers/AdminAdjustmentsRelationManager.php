<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Admin;
use App\Models\Transaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AdminAdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title       = 'Ajustes de balance (admin)';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'admin_adjustment'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('net_amount')
                    ->label('Monto')
                    ->formatStateUsing(fn(string $state): string =>
                        (float)$state >= 0
                            ? '+$' . number_format(abs((float)$state), 2)
                            : '-$' . number_format(abs((float)$state), 2)
                    )
                    ->color(fn(string $state): string => (float)$state >= 0 ? 'success' : 'danger'),

                TextColumn::make('field_adjusted')
                    ->label('Campo')
                    ->state(fn(Transaction $r): string => match(data_get($r->metadata, 'field_adjusted')) {
                        'balance_available',
                        'balance_in_operation' => 'En operación',
                        default                => data_get($r->metadata, 'field_adjusted') ?? '—',
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label('Motivo')
                    ->wrap()
                    ->limit(80),

                TextColumn::make('admin_name')
                    ->label('Admin')
                    ->state(function (Transaction $r): string {
                        $adminId = data_get($r->metadata, 'admin_id');
                        if (! $adminId) {
                            return '—';
                        }
                        if ($adminId === 'system') {
                            return 'Sistema';
                        }
                        if (\Illuminate\Support\Str::isUuid($adminId)) {
                            return Admin::find($adminId)?->name ?? "ID {$adminId}";
                        }
                        return "ID {$adminId}";
                    }),

                TextColumn::make('previous_value')
                    ->label('Valor anterior')
                    ->state(fn(Transaction $r): string =>
                        ($v = data_get($r->metadata, 'previous_value')) ? '$' . number_format((float)$v, 2) : '—'
                    )
                    ->color('gray'),

                TextColumn::make('new_value')
                    ->label('Valor nuevo')
                    ->state(fn(Transaction $r): string =>
                        ($v = data_get($r->metadata, 'new_value')) ? '$' . number_format((float)$v, 2) : '—'
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
