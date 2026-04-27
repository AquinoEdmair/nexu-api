<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositClientConfirmed;
use App\Models\Admin;
use App\Notifications\AdminAlertNotification;

final class NotifyAdminOnDepositClientConfirmed
{
    public function handle(DepositClientConfirmed $event): void
    {
        $amount   = number_format((float) $event->depositRequest->amount_expected, 2);
        $currency = $event->depositRequest->currency;
        $userName = $event->user->name;
        $url      = route('filament.admin.resources.deposit-requests.index');

        $notification = new AdminAlertNotification(
            type:        'admin_deposit_client_confirmed',
            mailSubject: "Depósito confirmado por cliente: {$userName}",
            title:       'Depósito reportado por el cliente',
            body:        "{$userName} reportó haber realizado el depósito de \${$amount} {$currency}.",
            actionUrl:   $url,
            actionLabel: 'Revisar depósito',
        );

        Admin::whereIn('role', ['super_admin', 'manager'])->get()->each->notify($notification);
    }
}
