<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Models\Admin;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Nuevo administrador')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(Admin::class, 'email')
                        ->maxLength(150),

                    Select::make('role')
                        ->label('Rol')
                        ->required()
                        ->options([
                            'super_admin' => 'Super Admin',
                            'manager'     => 'Manager',
                        ]),
                ])
                ->columns(2),

            Section::make('Acceso')
                ->schema([
                    Placeholder::make('password_info')
                        ->label('')
                        ->content(new HtmlString(
                            '<p class="text-sm text-gray-500">Se generará automáticamente una contraseña temporal y se mostrará al crear el administrador. El administrador deberá cambiarla en su primer acceso.</p>'
                        )),
                ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $tempPassword = Str::random(16);

        /** @var Admin $admin */
        $admin = Admin::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'password' => Hash::make($tempPassword, ['rounds' => Admin::BCRYPT_ROUNDS]),
        ]);

        activity()->causedBy(auth()->user())
            ->performedOn($admin)
            ->log(
                'Super admin ' . auth()->user()?->name . ' creó admin ' . $admin->email . ' con rol ' . $admin->role
            );

        session(['admin_temp_password' => $tempPassword]);

        Notification::make()
            ->title('Administrador creado')
            ->body("Contraseña temporal: <strong>{$tempPassword}</strong><br>Guárdala ahora — no se volverá a mostrar.")
            ->success()
            ->persistent()
            ->send();

        return $admin;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
