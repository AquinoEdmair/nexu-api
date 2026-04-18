<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Contactos Landing';
    protected static ?string $navigationGroup = 'Operaciones';
    protected static ?string $modelLabel = 'Mensaje de Contacto';
    protected static ?string $pluralModelLabel = 'Contactos Landing';
    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                \Filament\Forms\Components\TextInput::make('name')
                    ->label('Nombre'),
                \Filament\Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico'),
                \Filament\Forms\Components\TextInput::make('phone')
                    ->label('Teléfono'),
                \Filament\Forms\Components\TextInput::make('subject')
                    ->label('Asunto'),
                \Filament\Forms\Components\Textarea::make('message')
                    ->label('Mensaje')
                    ->columnSpanFull(),
                \Filament\Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'unread' => 'No leído',
                        'read' => 'Leído',
                        'replied' => 'Respondido',
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Asunto')
                    ->limit(30),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => match($state) {
                        'unread' => 'No leído',
                        'read'   => 'Leído',
                        'replied'=> 'Respondido',
                        default  => $state,
                    })
                    ->color(fn(string $state) => match($state) {
                        'unread' => 'danger',
                        'read'   => 'warning',
                        'replied'=> 'success',
                        default  => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'unread' => 'No leído',
                        'read' => 'Leído',
                        'replied' => 'Respondido',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver Detalles'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
        ];
    }
}
