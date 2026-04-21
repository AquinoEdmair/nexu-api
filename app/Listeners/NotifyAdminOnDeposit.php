<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DepositConfirmed;
use App\Models\Admin;
use App\Notifications\AdminAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyAdminOnDeposit implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(DepositConfirmed $event): void
    {
        $amount   = number_format((float) $event->netAmount, 2);
        $currency = $event->currency;
        $userName = $event->user->name;
        $url      = route('filament.admin.resources.transactions.index');

        $notification = new AdminAlertNotification(
            type:        'admin_deposit',
            mailSubject: "Nuevo depósito de {$userName}",
            title:       'Nuevo depósito confirmado',
            body:        "{$userName} realizó un depósito de \${$amount} {$currency}.",
            actionUrl:   $url,
            actionLabel: 'Ver transacciones',
        );

        Admin::whereIn('role', ['super_admin', 'manager'])->get()->each->notify($notification);
    }
}
