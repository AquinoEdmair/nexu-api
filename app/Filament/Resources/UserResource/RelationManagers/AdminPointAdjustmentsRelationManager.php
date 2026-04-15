<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Admin;
use App\Models\ElitePoint;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AdminPointAdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'elitePoints';
    protected static ?string $title       = 'Ajustes de puntos (admin)';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->where('description', 'like', 'admin:%'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('points')
                    ->label('Puntos')
                    ->formatStateUsing(fn(string $state): string =>
                        (float) $state >= 0
                            ? '+' . number_format(abs((float) $state), 2)
                            : '-'  . number_format(abs((float) $state), 2)
                    )
                    ->color(fn(string $state): string => (float) $state >= 0 ? 'success' : 'danger'),

                TextColumn::make('admin_name')
                    ->label('Admin')
                    ->state(function (ElitePoint $r): string {
                        $parts   = explode(':', $r->description ?? '', 3);
                        $adminId = $parts[1] ?? null;
                        if (! $adminId) {
                            return '—';
                        }
                        return Admin::find($adminId)?->name ?? "ID {$adminId}";
                    }),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->state(function (ElitePoint $r): string {
                        $parts = explode(':', $r->description ?? '', 3);
                        return $parts[2] ?? '—';
                    })
                    ->wrap()
                    ->limit(80),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
