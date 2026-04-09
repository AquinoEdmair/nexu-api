<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Admin;
use App\Services\AdminAuthService;
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

    public string $twoFactorCode           = '';
    public string $disableTwoFactorPassword = '';
    public string $recoveryCodesPassword   = '';

    /** @var array<string>|null */
    public ?array $shownRecoveryCodes = null;

    public ?string $qrCodeUrl  = null;
    public ?string $secretKey  = null;
    public bool    $showQrModal       = false;
    public bool    $showConfirmModal  = false;
    public bool    $showCodesModal    = false;

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

    public function enableTwoFactor(): void
    {
        /** @var Admin $admin */
        $admin = auth()->user();

        $dto = app(AdminAuthService::class)->generateTwoFactorSecret($admin);

        $this->qrCodeUrl        = $dto->qrCodeUrl;
        $this->secretKey        = $dto->secretKey;
        $this->showQrModal      = true;
        $this->showConfirmModal = false;
    }

    public function openConfirmModal(): void
    {
        $this->showQrModal      = false;
        $this->showConfirmModal = true;
    }

    public function confirmTwoFactor(): void
    {
        $this->validate(['twoFactorCode' => ['required', 'string', 'size:6']]);

        /** @var Admin $admin */
        $admin = auth()->user();

        try {
            $codes = app(AdminAuthService::class)->confirmTwoFactor($admin, $this->twoFactorCode);

            $this->shownRecoveryCodes = $codes;
            $this->showConfirmModal   = false;
            $this->showCodesModal     = true;
            $this->twoFactorCode      = '';

            Notification::make()
                ->title('2FA activado correctamente')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            $this->addError('twoFactorCode', $e->getMessage());
        }
    }

    public function disableTwoFactor(): void
    {
        $this->validate(['disableTwoFactorPassword' => ['required']]);

        /** @var Admin $admin */
        $admin = auth()->user();

        try {
            app(AdminAuthService::class)->disableTwoFactor($admin, $this->disableTwoFactorPassword);

            $this->disableTwoFactorPassword = '';

            Notification::make()
                ->title('2FA desactivado correctamente')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            $this->addError('disableTwoFactorPassword', $e->getMessage());
        }
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->validate(['recoveryCodesPassword' => ['required']]);

        /** @var Admin $admin */
        $admin = auth()->user();

        try {
            $codes = app(AdminAuthService::class)->regenerateRecoveryCodes($admin, $this->recoveryCodesPassword);

            $this->shownRecoveryCodes  = $codes;
            $this->recoveryCodesPassword = '';
            $this->showCodesModal      = true;

            Notification::make()
                ->title('Recovery codes regenerados correctamente')
                ->success()
                ->send();
        } catch (\InvalidArgumentException $e) {
            $this->addError('recoveryCodesPassword', $e->getMessage());
        }
    }

    public function closeModals(): void
    {
        $this->showQrModal      = false;
        $this->showConfirmModal = false;
        $this->showCodesModal   = false;
    }
}
