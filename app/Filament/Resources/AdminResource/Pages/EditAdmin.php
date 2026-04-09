<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Models\Admin;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Información del administrador')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('email')
                        ->label('Email')
                        ->disabled()
                        ->dehydrated(false),

                    Select::make('role')
                        ->label('Rol')
                        ->required()
                        ->options([
                            'super_admin' => 'Super Admin',
                            'manager'     => 'Manager',
                        ]),
                ])
                ->columns(2),
        ]);
    }

    protected function afterSave(): void
    {
        /** @var Admin $record */
        $record = $this->getRecord();

        activity()->causedBy(auth()->user())
            ->performedOn($record)
            ->log(
                'Super admin ' . auth()->user()?->name . ' editó admin ' . $record->email
            );

        Notification::make()
            ->title('Administrador actualizado correctamente')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
