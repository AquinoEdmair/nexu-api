<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalRequested;
use App\Models\Admin;
use App\Notifications\AdminAlertNotification;

final class NotifyAdminOnWithdrawalRequested
{

    public function handle(WithdrawalRequested $event): void
    {
        $amount   = number_format((float) $event->request->amount, 2);
        $currency = $event->request->currency;
        $userName = $event->user->name;
        $url      = route('filament.admin.resources.withdrawal-requests.index');

        $notification = new AdminAlertNotification(
            type:        'admin_withdrawal',
            mailSubject: "Solicitud de retiro de {$userName}",
            title:       'Nueva solicitud de retiro',
            body:        "{$userName} solicitó un retiro de \${$amount} {$currency}. Requiere aprobación.",
            actionUrl:   $url,
            actionLabel: 'Revisar solicitudes',
        );

        Admin::whereIn('role', ['super_admin', 'manager'])->get()->each->notify($notification);
    }
}
