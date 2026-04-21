<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Admin;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class AdminProfile extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $title          = 'Mi perfil';
    protected static string  $view           = 'filament.pages.admin-profile';
    protected static ?string $slug           = 'profile';

    public string $name = '';

    public string $currentPassword         = '';
    public string $newPassword             = '';
    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        /** @var Admin $admin */
        $admin      = auth()->user();
        $this->name = $admin->name;
    }

    public function saveProfile(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:100']]);

        /** @var Admin $admin */
        $admin = auth()->user();
        $admin->update(['name' => $this->name]);

        Notification::make()
            ->title('Perfil actualizado correctamente')
            ->success()
            ->send();
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword'         => ['required'],
            'newPassword'             => ['required', 'min:12', 'confirmed'],
            'newPasswordConfirmation' => ['required'],
        ]);

        /** @var Admin $admin */
        $admin = auth()->user();

        if (! Hash::check($this->currentPassword, $admin->password)) {
            $this->addError('currentPassword', 'La contraseña actual es incorrecta.');

            return;
        }

        $admin->update([
            'password' => Hash::make($this->newPassword, ['rounds' => Admin::BCRYPT_ROUNDS]),
        ]);

        $this->currentPassword         = '';
        $this->newPassword             = '';
        $this->newPasswordConfirmation = '';

        Notification::make()
            ->title('Contraseña actualizada correctamente')
            ->success()
            ->send();
    }

}
