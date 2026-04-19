<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TeamMemberResource\Pages\CreateTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\EditTeamMember;
use App\Filament\Resources\TeamMemberResource\Pages\ListTeamMembers;
use App\Models\TeamMember;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TeamMemberResource extends Resource
{
    protected static ?string $model = TeamMember::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Equipo';
    protected static ?string $navigationGroup = 'Contenido';
    protected static ?int    $navigationSort  = 1;
    protected static ?string $modelLabel      = 'Miembro';
    protected static ?string $pluralModelLabel = 'Equipo';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Información')->schema([
                FileUpload::make('photo_path')
                    ->label('Foto')
                    ->image()
                    ->disk('public')
                    ->directory('team')
                    ->imageResizeTargetWidth('400')
                    ->imageResizeTargetHeight('400')
                    ->nullable()
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100),

                TextInput::make('title')
                    ->label('Título / Profesión')
                    ->required()
                    ->maxLength(150),

                Textarea::make('bio')
                    ->label('Resumen breve')
                    ->rows(3)
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (): string => 'https://ui-avatars.com/api/?name=N&background=1a1a2e&color=fff'),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Título')
                    ->limit(40),

                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** @return array<\Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index'  => ListTeamMembers::route('/'),
            'create' => CreateTeamMember::route('/create'),
            'edit'   => EditTeamMember::route('/{record}/edit'),
        ];
    }
}
