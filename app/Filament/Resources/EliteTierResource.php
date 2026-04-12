<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\EliteTierResource\Pages\CreateEliteTier;
use App\Filament\Resources\EliteTierResource\Pages\EditEliteTier;
use App\Filament\Resources\EliteTierResource\Pages\ListEliteTiers;
use App\Models\EliteTier;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class EliteTierResource extends Resource
{
    protected static ?string $model = EliteTier::class;

    protected static ?string $navigationIcon  = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Niveles Élite';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int    $navigationSort  = 2;
    protected static ?string $modelLabel      = 'Nivel';
    protected static ?string $pluralModelLabel = 'Niveles Élite';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identificación')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Ej. Bronce'),

                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->unique(EliteTier::class, 'slug', ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder('ej. bronze')
                        ->helperText('Identificador único. Solo letras minúsculas y guiones.'),

                    TextInput::make('sort_order')
                        ->label('Orden de progresión')
                        ->required()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Menor número = nivel más bajo. Debe ser único entre niveles activos.'),

                    Toggle::make('is_active')
                        ->label('Activo')
                        ->default(true)
                        ->helperText('Solo los niveles activos se asignan automáticamente.'),
                ]),

            Section::make('Rango de puntos')
                ->columns(2)
                ->schema([
                    TextInput::make('min_points')
                        ->label('Puntos mínimos')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(1)
                        ->placeholder('0'),

                    TextInput::make('max_points')
                        ->label('Puntos máximos')
                        ->numeric()
                        ->minValue(1)
                        ->step(1)
                        ->nullable()
                        ->placeholder('Sin techo (dejar vacío para el nivel más alto)')
                        ->helperText('Vacío = este nivel no tiene límite superior.'),
                ]),

            Section::make('Multiplicador y comisiones')
                ->columns(3)
                ->description('El multiplicador escala los puntos que gana el usuario por cada dólar. Las comisiones se aplican al referrer cuando alguien a quien refirió deposita.')
                ->schema([
                    TextInput::make('multiplier')
                        ->label('Multiplicador de puntos')
                        ->required()
                        ->numeric()
                        ->minValue(1.0)
                        ->step(0.01)
                        ->default(1.00)
                        ->suffix('×')
                        ->helperText('1.5 = 1.5 puntos por cada $1 depositado o ganado.'),

                    TextInput::make('first_deposit_commission_rate')
                        ->label('Comisión — 1er depósito')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.0001)
                        ->default(0.0000)
                        ->suffix('%')
                        ->helperText('Ej. 0.05 = 5%. Comisión que gana el referrer en el primer depósito de su referido.'),

                    TextInput::make('recurring_commission_rate')
                        ->label('Comisión — depósitos siguientes')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.0001)
                        ->default(0.0000)
                        ->suffix('%')
                        ->helperText('Ej. 0.02 = 2%. Aplica a todos los depósitos siguientes del referido.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->color('gray')
                    ->fontFamily('mono'),

                TextColumn::make('min_points')
                    ->label('Puntos mínimos')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),

                TextColumn::make('max_points')
                    ->label('Puntos máximos')
                    ->numeric(decimalPlaces: 0)
                    ->placeholder('∞')
                    ->sortable(),

                TextColumn::make('multiplier')
                    ->label('Multiplicador')
                    ->suffix('×')
                    ->sortable(),

                TextColumn::make('first_deposit_commission_rate')
                    ->label('1er depósito')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state * 100, 2) . '%')
                    ->color('success'),

                TextColumn::make('recurring_commission_rate')
                    ->label('Recurrente')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state * 100, 2) . '%')
                    ->color('info'),

                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (EliteTier $record, DeleteAction $action): void {
                        if ($record->users()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar')
                                ->body("Hay {$record->users()->count()} usuario(s) en este nivel. Reasígnalos antes de eliminar.")
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index'  => ListEliteTiers::route('/'),
            'create' => CreateEliteTier::route('/create'),
            'edit'   => EditEliteTier::route('/{record}/edit'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return true; // Controlled in table action above via before() hook.
    }
}
