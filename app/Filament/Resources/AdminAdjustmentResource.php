<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAdjustmentResource\Pages\ListAdminAdjustments;
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

                TextColumn::make('metadata->field_adjusted')
                    ->label('Campo')
                    ->formatStateUsing(fn(?string $state): string => match($state) {
                        'balance_available'    => 'Disponible',
                        'balance_in_operation' => 'En operación',
                        default                => $state ?? '—',
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('description')
                    ->label('Motivo')
                    ->wrap()
                    ->limit(80)
                    ->searchable(),

                TextColumn::make('metadata->admin_id')
                    ->label('Admin ID')
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('metadata->previous_value')
                    ->label('Antes')
                    ->formatStateUsing(fn(?string $state): string => $state ? '$' . number_format((float)$state, 2) : '—')
                    ->color('gray'),

                TextColumn::make('metadata->new_value')
                    ->label('Después')
                    ->formatStateUsing(fn(?string $state): string => $state ? '$' . number_format((float)$state, 2) : '—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('field_adjusted')
                    ->label('Campo')
                    ->options([
                        'balance_available'    => 'Balance disponible',
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
