<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAdjustmentResource\Pages\ListAdminAdjustments;
use App\Models\Admin;
use App\Models\Transaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AdminAdjustmentResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Ajustes manuales';
    protected static ?string $navigationGroup = 'Finanzas';
    protected static ?int    $navigationSort  = 5;
    protected static ?string $modelLabel      = 'Ajuste';
    protected static ?string $pluralModelLabel = 'Ajustes manuales';

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
                    ->description(fn(Transaction $r): string => $r->user?->email ?? '')
                    ->searchable(['users.name', 'users.email'])
                    ->sortable(),

                TextColumn::make('net_amount')
                    ->label('Monto')
                    ->formatStateUsing(fn(string $state): string =>
                        (float)$state >= 0
                            ? '+$' . number_format(abs((float)$state), 2)
                            : '-$' . number_format(abs((float)$state), 2)
                    )
                    ->color(fn(string $state): string => (float)$state >= 0 ? 'success' : 'danger')
                    ->sortable(),

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
                    ->limit(80)
                    ->searchable(),

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
                    ->label('Antes')
                    ->state(fn(Transaction $r): string =>
                        ($v = data_get($r->metadata, 'previous_value')) ? '$' . number_format((float)$v, 2) : '—'
                    )
                    ->color('gray'),

                TextColumn::make('new_value')
                    ->label('Después')
                    ->state(fn(Transaction $r): string =>
                        ($v = data_get($r->metadata, 'new_value')) ? '$' . number_format((float)$v, 2) : '—'
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('field_adjusted')
                    ->label('Campo')
                    ->options([
                        'balance_in_operation' => 'En operación',
                    ])
                    ->query(fn(Builder $query, array $data): Builder =>
                        $data['value']
                            ? $query->whereJsonContains('metadata->field_adjusted', $data['value'])
                            : $query
                    ),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListAdminAdjustments::route('/'),
        ];
    }

    /** @return Builder<Transaction> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user:id,name,email'])
            ->where('type', 'admin_adjustment');
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
}
