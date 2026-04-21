<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\TicketCreated;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\TicketAdminRepliedNotification;
use App\Notifications\TicketClosedNotification;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Support\Facades\DB;

final class SupportTicketService
{
    /**
     * Create a new support ticket with the user's first message.
     *
     * @throws \Throwable
     */
    public function create(User $user, string $subject, string $message): SupportTicket
    {
        $ticket = DB::transaction(function () use ($user, $subject, $message): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'status'  => 'open',
            ]);

            SupportTicketMessage::create([
                'ticket_id'   => $ticket->id,
                'sender_type' => 'user',
                'sender_id'   => (string) $user->id,
                'body'        => $message,
            ]);

            return $ticket;
        });

        $user->notify(new TicketCreatedNotification($ticket));

        event(new TicketCreated($ticket, $user));

        return $ticket;
    }

    /**
     * Add a reply message to an existing ticket.
     * Moves status to in_progress on first admin reply.
     *
     * @throws \DomainException
     * @throws \Throwable
     */
    public function reply(SupportTicket $ticket, string $body, string $senderType, string $senderId): SupportTicketMessage
    {
        if (! $ticket->isOpen()) {
            throw new \DomainException('No se puede responder a un ticket cerrado.');
        }

        $message = DB::transaction(function () use ($ticket, $body, $senderType, $senderId): SupportTicketMessage {
            if ($senderType === 'admin' && $ticket->status === 'open') {
                $ticket->update(['status' => 'in_progress']);
            }

            return SupportTicketMessage::create([
                'ticket_id'   => $ticket->id,
                'sender_type' => $senderType,
                'sender_id'   => $senderId,
                'body'        => $body,
            ]);
        });

        // Notify user when admin replies
        if ($senderType === 'admin') {
            $ticket->load('user');
            $ticket->user->notify(new TicketAdminRepliedNotification($ticket, $body));
        }

        return $message;
    }

    /**
     * Close a ticket (admin only).
     *
     * @throws \DomainException
     * @throws \Throwable
     */
    public function close(SupportTicket $ticket, Admin $admin): SupportTicket
    {
        if (! $ticket->isOpen()) {
            throw new \DomainException('El ticket ya está cerrado.');
        }

        $ticket->update([
            'status'    => 'closed',
            'closed_by' => $admin->id,
            'closed_at' => now(),
        ]);

        $ticket->load('user');
        $ticket->user->notify(new TicketClosedNotification($ticket));

        activity()
            ->causedBy($admin)
            ->performedOn($ticket)
            ->log("Admin {$admin->name} cerró el ticket {$ticket->id}");

        return $ticket;
    }
}
