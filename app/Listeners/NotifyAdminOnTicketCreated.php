<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Models\Admin;
use App\Notifications\AdminAlertNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifyAdminOnTicketCreated implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(TicketCreated $event): void
    {
        $userName = $event->user->name;
        $subject  = $event->ticket->subject;
        $url      = route('filament.admin.resources.support-tickets.index');

        $notification = new AdminAlertNotification(
            type:        'admin_ticket',
            mailSubject: "Nuevo ticket: {$subject}",
            title:       'Nuevo ticket de soporte',
            body:        "{$userName} abrió un ticket: \"{$subject}\".",
            actionUrl:   $url,
            actionLabel: 'Ver tickets',
        );

        Admin::whereIn('role', ['super_admin', 'manager'])->get()->each->notify($notification);
    }
}
