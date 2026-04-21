<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositInitiated;
use App\Models\Admin;
use App\Notifications\AdminAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyAdminOnDepositInitiated implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(DepositInitiated $event): void
    {
        $amount   = number_format((float) $event->amount, 2);
        $currency = $event->currency;
        $userName = $event->user->name;
        $url      = route('filament.admin.resources.deposit-invoices.index');

        $notification = new AdminAlertNotification(
            type:        'admin_deposit_initiated',
            mailSubject: "Nuevo depósito iniciado: {$userName}",
            title:       'Nuevo depósito iniciado',
            body:        "{$userName} generó una dirección para depositar \${$amount} {$currency}.",
            actionUrl:   $url,
            actionLabel: 'Ver facturas',
        );

        Admin::whereIn('role', ['super_admin', 'manager'])->get()->each->notify($notification);
    }
}
