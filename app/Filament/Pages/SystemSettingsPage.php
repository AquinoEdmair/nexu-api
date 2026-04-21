<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettingsPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $title           = 'Configuración del sistema';
    protected static string  $view            = 'filament.pages.system-settings';
    protected static ?string $slug            = 'settings';
    protected static ?int    $navigationSort  = 99;

    public string $adminNotificationEmail = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        $this->adminNotificationEmail = SystemSetting::get('admin_notification_email');
    }

    public function save(): void
    {
        $this->validate([
            'adminNotificationEmail' => ['nullable', 'email', 'max:255'],
        ]);

        SystemSetting::set(
            key:         'admin_notification_email',
            value:       $this->adminNotificationEmail ?: null,
            description: 'Email adicional que recibe BCC de todas las alertas del panel admin.',
        );

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }
}
