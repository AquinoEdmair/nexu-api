<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Admin;
use App\Notifications\AdminAlertNotification;
use Illuminate\Auth\Events\Registered;

final class NotifyAdminOnNewUser
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        $url  = route('filament.admin.resources.users.index');

        $notification = new AdminAlertNotification(
            type:        'admin_new_user',
            mailSubject: "Nuevo usuario registrado: {$user->name}",
            title:       'Nuevo usuario registrado',
            body:        "{$user->name} ({$user->email}) se registró en la plataforma.",
            actionUrl:   $url,
            actionLabel: 'Ver usuarios',
        );

        Admin::where('role', 'super_admin')->get()->each->notify($notification);
    }
}
