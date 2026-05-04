<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    
    protected static ?string $navigationGroup = 'Marketing';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Principal')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->label('Título de la campaña'),
                        
                        Forms\Components\Select::make('type')
                            ->options([
                                'informative' => 'Informativa (solo texto/imagen)',
                                'action'      => 'De Acción (con botón)',
                            ])
                            ->required()
                            ->label('Tipo de Campaña'),

                        Forms\Components\Select::make('channel')
                            ->options([
                                'modal' => 'Modal (Web Dashboard)',
                                'email' => 'Email',
                                'both'  => 'Modal + Email',
                            ])
                            ->required()
                            ->label('Canal de entrega'),
                    ])->columns(3),

                Forms\Components\Section::make('Contenido')
                    ->schema([
                        Forms\Components\TextInput::make('image_url')
                            ->url()
                            ->maxLength(255)
                            ->label('URL de la Imagen (opcional)'),
                            
                        Forms\Components\RichEditor::make('description')
                            ->label('Descripción / Contenido HTML')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Call To Action (Botón)')
                    ->schema([
                        Forms\Components\Select::make('cta_type')
                            ->options([
                                'none'       => 'Ninguno',
                                'redirect'   => 'Redirección a URL',
                                'api_action' => 'Ejecutar Acción API',
                            ])
                            ->default('none')
                            ->label('Tipo de Acción'),

                        Forms\Components\TextInput::make('cta_text')
                            ->maxLength(100)
                            ->label('Texto del botón (Ej. "Invertir ahora")')
                            ->hidden(fn (Forms\Get $get): bool => $get('cta_type') === 'none'),

                        Forms\Components\TextInput::make('cta_url')
                            ->maxLength(255)
                            ->label('URL de destino')
                            ->hidden(fn (Forms\Get $get): bool => $get('cta_type') === 'none'),
                    ])->columns(3),

                Forms\Components\Section::make('Segmentación y Prioridad')
                    ->schema([
                        Forms\Components\Select::make('target_segment')
                            ->options([
                                'all'         => 'Todos los usuarios',
                                'active'      => 'Usuarios Activos',
                                'inactive'    => 'Usuarios Inactivos',
                                'has_balance' => 'Con saldo en la plataforma',
                                'no_deposit'  => 'Sin depósitos previos',
                                'referred'    => 'Usuarios invitados por alguien',
                                'custom'      => 'Personalizado (Avanzado)',
                            ])
                            ->required()
                            ->label('Segmento Objetivo')
                            ->live(),

                        Forms\Components\Select::make('custom_target_query')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => \App\Models\User::where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')->toArray())
                            ->getOptionLabelsUsing(fn (array $values): array => \App\Models\User::whereIn('id', $values)->pluck('name', 'id')->toArray())
                            ->label('Seleccionar Usuarios')
                            ->visible(fn (Forms\Get $get): bool => $get('target_segment') === 'custom')
                            ->required(fn (Forms\Get $get): bool => $get('target_segment') === 'custom'),

                        Forms\Components\Select::make('display_frequency')
                            ->options([
                                'once'           => 'Mostrar solo una vez',
                                'until_accepted' => 'Mostrar hasta que el usuario acepte/rechace',
                                'always'         => 'Mostrar siempre (no recomendado para modales)',
                            ])
                            ->default('once')
                            ->required()
                            ->label('Frecuencia de visualización'),

                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->label('Prioridad (0-100)')
                            ->helperText('Si hay múltiples campañas, la de mayor prioridad se mostrará primero.'),
                    ])->columns(3),

                Forms\Components\Section::make('Programación')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label('Fecha de Inicio')
                            ->helperText('Dejar en blanco para iniciar inmediatamente tras activar.'),
                            
                        Forms\Components\DateTimePicker::make('end_at')
                            ->label('Fecha de Fin')
                            ->helperText('Dejar en blanco para que no tenga fin automático.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activar Campaña')
                            ->helperText('Al activar, se calcularán los destinatarios y se lanzará. Asegúrate de haber revisado todo.')
                            ->default(false)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->label('Título'),
                    
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->label('Canal'),
                    
                Tables\Columns\TextColumn::make('target_segment')
                    ->badge()
                    ->label('Segmento'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Activa'),
                    
                Tables\Columns\TextColumn::make('priority')
                    ->numeric()
                    ->sortable()
                    ->label('Prioridad'),
                    
                Tables\Columns\TextColumn::make('start_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit'   => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
