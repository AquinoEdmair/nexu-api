<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminPointAdjustmentResource\Pages\ListAdminPointAdjustments;
use App\Models\Admin;
use App\Models\ElitePoint;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AdminPointAdjustmentResource extends Resource
{
    protected static ?string $model = ElitePoint::class;

    protected static ?string $navigationIcon  = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Ajustes de puntos';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int    $navigationSort  = 6;
    protected static ?string $modelLabel      = 'Ajuste de puntos';
    protected static ?string $pluralModelLabel = 'Ajustes de puntos';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->description(fn(ElitePoint $r): string => $r->user?->email ?? '')
                    ->searchable(['users.name', 'users.email'])
                    ->sortable(),

                TextColumn::make('points')
                    ->label('Puntos')
                    ->formatStateUsing(fn(string $state): string =>
                        (float) $state >= 0
                            ? '+' . number_format(abs((float) $state), 2)
                            : '-'  . number_format(abs((float) $state), 2)
                    )
                    ->color(fn(string $state): string => (float) $state >= 0 ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('admin_name')
                    ->label('Admin')
                    ->state(function (ElitePoint $r): string {
                        // description format: "admin:{adminId}:{reason}"
                        $parts   = explode(':', $r->description ?? '', 3);
                        $adminId = $parts[1] ?? null;
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

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->state(function (ElitePoint $r): string {
                        // description format: "admin:{adminId}:{reason}"
                        $parts = explode(':', $r->description ?? '', 3);
                        return $parts[2] ?? '—';
                    })
                    ->wrap()
                    ->limit(80)
                    ->searchable(query: fn(Builder $query, string $search): Builder =>
                        $query->where('description', 'like', "%{$search}%")
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListAdminPointAdjustments::route('/'),
        ];
    }

    /** @return Builder<ElitePoint> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user:id,name,email'])
            ->where('description', 'like', 'admin:%');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
}
